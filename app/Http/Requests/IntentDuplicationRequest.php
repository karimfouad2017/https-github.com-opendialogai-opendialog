<?php

namespace App\Http\Requests;

use App\Rules\TurnExists;

class IntentDuplicationRequest extends ConversationObjectDuplicationRequest
{
    use ConversationObjectRequestTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $this->merge(['destination' => $this->get('destination')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return parent::rules() + ['destination' => ['nullable', new TurnExists]];
    }
}
