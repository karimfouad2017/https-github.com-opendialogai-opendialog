<?php

namespace App\Http\Requests;

use App\Rules\ComponentConfigurationRule;
use App\Rules\ComponentRegistrationRule;
use App\Rules\ScenarioExists;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenDialogAi\ActionEngine\Service\ActionComponentServiceInterface;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Components\Exceptions\UnknownComponentTypeException;
use OpenDialogAi\Core\Components\Helper\ComponentHelper;
use OpenDialogAi\InterpreterEngine\Service\InterpreterComponentServiceInterface;
use OpenDialogAi\PlatformEngine\Services\PlatformComponentServiceInterface;

/**
 * @property $name string
 * @property $scenario_id string
 * @property $component_id string
 * @property $configuration array
 */
class ComponentConfigurationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [
            'scenario_id' => [
                'bail',
                Rule::requiredIf($this->method() == 'POST' || !is_null($this->name)),
                'string',
                'filled',
                new ScenarioExists,
            ],
            'name' => [
                'bail',
                Rule::requiredIf($this->method() == 'POST' || !is_null($this->scenario_id)),
                'string',
                'filled',
                Rule::unique('component_configurations')->where(function ($query) {
                    if ($this->route('component_configuration')) {
                        $query->where('id', '!=', $this->route('component_configuration')->id);
                    }

                    return $query->where('scenario_id', $this->scenario_id);
                }),
            ],
            'component_id' => [
                'bail',
                Rule::requiredIf($this->method() == 'POST' || !is_null($this->configuration)),
                'string',
                'filled',
                new ComponentRegistrationRule
            ],
            'configuration' => [
                'bail',
                Rule::requiredIf($this->method() == 'POST' || !is_null($this->component_id)),
                'array',
                new ComponentConfigurationRule($this->component_id ?? '')
            ],
            'active' => [
                'bail',
                'boolean',
            ]
        ];

        $rules = $this->mergeCustomRules($rules);

        return $rules;
    }

    protected function prepareForValidation()
    {
        if ($this->route('component_configuration') && $this->name) {
            /** @var ComponentConfiguration $configuration */
            $configuration = $this->route('component_configuration');

            $this->merge([
                'scenario_id' => $configuration->scenario_id,
            ]);
        }
    }

    protected function addConfigurationRules(): array
    {
        $componentId = $this->component_id ?? '';

        try {
            $type = ComponentHelper::parseComponentId($componentId);
        } catch (UnknownComponentTypeException $e) {
            return [];
        }

        $component = null;

        switch ($type) {
            case ComponentHelper::PLATFORM:
                $component = resolve(PlatformComponentServiceInterface::class)->get($componentId);
                break;
            case ComponentHelper::INTERPRETER:
                $component = resolve(InterpreterComponentServiceInterface::class)->get($componentId);
                break;
            case ComponentHelper::ACTION:
                $component = resolve(ActionComponentServiceInterface::class)->get($componentId);
                break;
        }

        if ($component) {
            return $component::getConfigurationRules();
        }

        return [];
    }

    protected function mergeCustomRules($rules): array
    {
        $configRules = $this->addConfigurationRules();

        $rules = array_merge($rules, $configRules);

        foreach ($configRules as $key => $configRule) {
            if (array_key_exists($key, $rules)) {
                if (is_array($configRule)) {
                    $rules[$key] = array_merge($rules[$key], $configRule);
                }
                continue;
            }
        }

        return $rules;
    }
}
