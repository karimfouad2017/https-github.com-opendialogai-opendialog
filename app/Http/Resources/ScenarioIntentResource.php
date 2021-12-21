<?php


namespace App\Http\Resources;

use App\Http\Facades\Serializer;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenDialogAi\Core\Conversation\Action;
use OpenDialogAi\Core\Conversation\Behavior;
use OpenDialogAi\Core\Conversation\Condition;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Conversation\MessageTemplate;
use OpenDialogAi\Core\Conversation\Scene;
use OpenDialogAi\Core\Conversation\Transition;
use OpenDialogAi\Core\Conversation\Turn;
use OpenDialogAi\Core\Conversation\VirtualIntent;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

class ScenarioIntentResource extends JsonResource
{
    public static $wrap = null;

    public static array $fields = [
        AbstractNormalizer::ATTRIBUTES => [
            Intent::UID,
            Intent::OD_ID,
            Intent::NAME,
            Intent::SPEAKER,
            Intent::SAMPLE_UTTERANCE,
            Intent::CREATED_AT,
            Intent::UPDATED_AT,
            Intent::TURN => [
                Turn::UID,
                Turn::OD_ID,
                Turn::NAME,
                Turn::SCENE => [
                    Scene::UID,
                    Scene::OD_ID,
                    Scene::NAME,
                    Scene::CONVERSATION => [
                        Scene::UID,
                        Scene::OD_ID,
                        Scene::NAME,
                    ]
                ]
            ]
        ]
    ];

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return Serializer::normalize($this->resource, 'json', self::$fields);
    }
}
