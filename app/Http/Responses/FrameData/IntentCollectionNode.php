<?php

namespace App\Http\Responses\FrameData;

use OpenDialogAi\Core\Conversation\IntentCollection;
use OpenDialogAi\Core\Conversation\Turn;

class IntentCollectionNode extends BaseNode
{
    public static function fromTurn(Turn $turn, IntentCollection $intents, $type, $groupId = null)
    {
        $intentCollection = new static(
            sprintf('%s Intents', ucfirst($type)),
            sprintf('%s_%s', $turn->getUid(), $type),
            $turn->getUid()
        );

        $intentCollection->status = self::NOT_CONSIDERED;

        $intentCollection->speaker = $intents->first() ? $intents->first()->getSpeaker() : "empty";
        $intentCollection->type = sprintf('%s_intents', $type);

        if ($groupId) {
            $intentCollection->groupId = $groupId;
        }

        return $intentCollection;
    }
}