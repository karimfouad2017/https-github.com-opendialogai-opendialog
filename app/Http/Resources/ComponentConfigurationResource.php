<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use OpenDialogAi\ActionEngine\Service\ActionComponentServiceInterface;
use OpenDialogAi\Core\Components\Helper\ComponentHelper;
use OpenDialogAi\InterpreterEngine\Service\InterpreterComponentServiceInterface;
use OpenDialogAi\PlatformEngine\Services\PlatformComponentServiceInterface;

class ComponentConfigurationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $componentId = $this->component_id;
        $type = ComponentHelper::parseComponentId($componentId);

        $component = null;

        switch ($type) {
            case 'platform':
                $component = resolve(PlatformComponentServiceInterface::class)->get($componentId);
                break;
            case 'interpreter':
                $component = resolve(InterpreterComponentServiceInterface::class)->get($componentId);
                break;
            case 'action':
                $component = resolve(ActionComponentServiceInterface::class)->get($componentId);
                break;
        }

        /** @var array $hiddenFields */
        $hiddenFields = $component::getConfigurationClass()::getHiddenFields();

        $originalArray = parent::toArray($request);

        $finalArray = $this->filterHiddenFields($originalArray, $hiddenFields);

        return $finalArray;
    }

    protected function filterHiddenFields($originalArray, $hiddenFields)
    {
        $finalArray = [];

        foreach ($originalArray as $key => $value) {
            if (!in_array($key, $hiddenFields)) {
                if (is_array($value)) {
                    $finalArray[$key] = $this->filterHiddenFields($value, $hiddenFields);
                    continue;
                }

                $finalArray[$key] = $value;
            }
        }

        return $finalArray;
    }
}
