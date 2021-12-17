<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use OpenDialogAi\Core\Conversation\Exceptions\ConversationObjectNotFoundException;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;

class TurnExists implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  string  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        try {
            ConversationDataClient::getTurnByUid($value);
            return true;
        } catch (ConversationObjectNotFoundException $exception) {
            return false;
        }
    }

    public function message()
    {
        return 'The provided turn does not exist.';
    }
}
