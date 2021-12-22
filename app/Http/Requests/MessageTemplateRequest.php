<?php


namespace App\Http\Requests;

use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\PlatformEngine\Components\BasePlatform;
use OpenDialogAi\PlatformEngine\Components\WebchatPlatform;
use OpenDialogAi\PlatformEngine\Services\PlatformComponentServiceInterface;
use OpenDialogAi\ResponseEngine\Formatters\MessageFormatterInterface;
use OpenDialogAi\ResponseEngine\Rules\MessageXML;

class MessageTemplateRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $messageXmlRule = $this->getMessageXmlRule();

        return [
            'id' => 'string',
            'od_id' => 'string',
            'name' => 'string',
            'description' => 'string',
            'behaviors' => 'array',
            'conditions' => 'array',
            'message_markup' => [
                Rule::requiredIf($this->method() === 'POST'),
                $messageXmlRule,
            ]
        ];
    }

    /**
     * @return MessageXML
     */
    private function getMessageXmlRule(): MessageXML
    {
        /** @var Intent $intent */
        $intent = $this->route('intent');

        $intent = ConversationDataClient::getScenarioWithFocusedIntent($intent->getUid());

        /** @var ComponentConfiguration $configuration */
        $configuration = ComponentConfiguration::byScenario($intent->getScenario()->getUid())
            ->platforms()
            ->first();

        if ($configuration) {
            $componentId = $configuration->component_id;
        } else {
            $componentId = WebchatPlatform::getComponentId();
        }

        /** @var class-string<BasePlatform> $componentClass */
        try {
            $componentClass = resolve(PlatformComponentServiceInterface::class)->get($componentId);
        } catch (Exception $e) {
            $componentClass = WebchatPlatform::class;
        }

        /** @var class-string<MessageFormatterInterface> $componentClass */
        $formatterClass = $componentClass::getFormatterClass();

        return $formatterClass::getMessageXmlRule();
    }
}
