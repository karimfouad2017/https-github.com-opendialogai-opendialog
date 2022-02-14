<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScenarioOverviewRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge(
            [
                'scenario' => $this->get('scenario'),
                'level' => $this->get('level'),
                'include' => $this->get('include'),
                'exclude' => $this->get('exclude'),
            ]
        );
    }
    public function rules()
    {
        return [
            'scenario' => 'required|string',
            'level' => 'required|int',
            'include' => 'nullable|string',
            'exclude' => 'nullable|string',
        ];
    }
}
