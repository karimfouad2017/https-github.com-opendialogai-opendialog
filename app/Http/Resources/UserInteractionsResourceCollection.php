<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;

class UserInteractionsResourceCollection extends ResourceCollection
{
    protected Carbon $from;
    protected Carbon $to;

    /**
     * @param Carbon $from Start date from where this resource starts
     * @param Carbon $to End time from where this resource starts
     * @param mixed $resource Messages collection
     */
    public function __construct(Carbon $from, Carbon $to, $resource)
    {
        $this->from = $from;
        $this->to = $to;
        parent::__construct($resource);
    }

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $formatted = [];
        $messages = $this->collection->groupBy('user_id');
        foreach($messages as $key => $interactions) {
            $formatted[] = $this->formatData($key, $interactions);
        }
        return $formatted;
    }

    /**
     * Formats interactions data
     *
     * @param string $key
     * @param Collection $interactions
     * @return array
     */
    protected function formatData(string $key, Collection $interactions): array
    {
        $result = [
            $key => [
                'chatbot_user_data' => $interactions[0]->user,
                'from' => $this->from,
                'to' => $this->to,
                'interactions' => []
            ]
        ];
        $result[$key]['interactions'][] = $this->formatInteractions($interactions);
        return $result;
    }

    /**
     * Format specific interaction data
     *
     * @param Collection $interactions
     * @return array
     */
    protected function formatInteractions(Collection $interactions): array
    {
        $result = [];
        foreach($interactions as $interaction) {
            $interactionData =  [
                'type' => $interaction->type,
                'date' => $interaction->created_at,
                'text' => $interaction->message,
                'data' => []
            ];

            $attributes = $interaction->data;
            $data = [];
            foreach($attributes as $name => $attribute) {
                $data[$name] = $attribute;
            }
            $interactionData['data'][] = $data;
            $result[] = $interactionData;
        }
        return $result;
    }
}
