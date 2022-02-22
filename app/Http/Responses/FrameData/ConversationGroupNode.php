<?php

namespace App\Http\Responses\FrameData;

class ConversationGroupNode extends BaseNode
{
    public string $type = 'conversation_group';

    public function __construct(string $label, string $id)
    {
        parent::__construct($label, "group_$id");
    }
}
