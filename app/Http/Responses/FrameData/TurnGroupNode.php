<?php

namespace App\Http\Responses\FrameData;

class TurnGroupNode extends BaseNode
{
    public string $type = 'turn_group';

    public function __construct(string $label, string $id, $groupId)
    {
        parent::__construct($label, "group_$id");
        $this->groupId = $groupId;
    }
}
