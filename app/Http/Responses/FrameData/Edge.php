<?php

namespace App\Http\Responses\FrameData;

/**
 * An edge represents a connection between 2 nodes
 */
class Edge
{
    const ID = 'id';
    const SOURCE = 'source';
    const TARGET = 'target';
    const STATUS = 'status';

    public string $target;
    public string $source;
    public string $status;

    /**
     * @param string $target
     * @param string $source
     * @param string $status
     */
    public function __construct(string $target, string $source, string $status)
    {
        $this->target = $target;
        $this->source = $source;
        $this->status = $status;
    }

    public static function startingEdge($targetId, $sourceId): Edge
    {
        return new self($targetId, $sourceId, "starting");
    }

    public static function openingEdge($targetId, $sourceId): Edge
    {
        return new self($targetId, $sourceId, "opening");
    }

    public static function transitionEdge($targetId, $sourceId): Edge
    {
        return new self($targetId, $sourceId, "transition");
    }

    public function toArray(): array
    {
        return [
            self::ID => sprintf("%s_%s", $this->source, $this->target),
            self::SOURCE => $this->source,
            self::TARGET => $this->target,
            self::STATUS => $this->status,
        ];
    }
}
