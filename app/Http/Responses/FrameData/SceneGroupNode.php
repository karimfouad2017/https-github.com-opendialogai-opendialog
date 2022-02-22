<?php

namespace App\Http\Responses\FrameData;

class SceneGroupNode extends BaseNode
{
    public string $type = 'scene_group';

    public function __construct(string $label, string $id, $groupId)
    {
        parent::__construct($label, "group_$id");
        $this->groupId = $groupId;
    }
}
