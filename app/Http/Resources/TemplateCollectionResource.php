<?php

namespace App\Http\Resources;

use App\Template;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenDialogAi\Core\Reflection\Helper\ReflectionHelperInterface;

class TemplateCollectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $root = parent::toArray($request);

        // Strip any array keys from the description data
        $root['description'] = is_array($this->resource->description) ?
            array_values($this->resource->description) :
            $this->resource->description;

        $platforms = $this->resource->templates->transform(
            fn (Template $template) => $template->platform_id
        );

        $allPlatforms = $this->getRegisteredPlatforms();

        $root['platforms'] = $platforms->toArray();

        $root['all'] = false;

        return $root;
    }

    /**
     * @return array
     */
    protected function getRegisteredPlatforms(): array
    {
        $platformEngineReflection = resolve(ReflectionHelperInterface::class)->getPlatformEngineReflection();
        return array_keys($platformEngineReflection->getAvailablePlatforms()->toArray());
    }
}
