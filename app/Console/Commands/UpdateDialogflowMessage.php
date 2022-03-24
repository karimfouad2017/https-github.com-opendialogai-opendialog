<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use OpenDialogAi\Core\Conversation\Facades\MessageTemplateDataClient;
use OpenDialogAi\Core\Conversation\MessageTemplate;

class UpdateDialogflowMessage extends Command
{
    const REGEX = '/{[[:space:]]*user.dialogflow_message[[:space:]]*}/';

    protected $signature = 'attribute_message:update';

    protected $description = "Updates any messages that directly use user.dialogflow_message in a message template and replaces 
    with an attribute message";

    public function handle()
    {
        $messageTemplates = MessageTemplateDataClient::getAllMessageTemplates();

        $messageTemplates->each(function (MessageTemplate $messageTemplate) {
            $messageMarkup = $messageTemplate->getMessageMarkup();
            if ($this->containsDialogflowMessage($messageMarkup)) {
                $messageMarkup = $this->replaceWithAttributeMessage($messageMarkup);
                $messageTemplate->setMessageMarkup($messageMarkup);
                MessageTemplateDataClient::updateMessageTemplate($messageTemplate);
                $this->info(sprintf("Updating message template with Id %s", $messageTemplate->getUid()));
            }
        });
    }

    /**
     * Checks if the message mark up contains a dialogflow_message attribute from the user context in standard attribute notation
     *
     * @param string $messageMarkup
     * @return bool
     */
    private function containsDialogflowMessage(string $messageMarkup)
    {
        return
            preg_match(self::REGEX, $messageMarkup) &&
            !Str::contains($messageMarkup, "<attribute-message>");
    }

    /**
     * Replaces the user.dialogflow_message attribute with an attribute mesasge without the attribute curly brace notation
     *
     * @param $messageMarkup
     * @return array|string|string[]|null
     */
    private function replaceWithAttributeMessage($messageMarkup)
    {
        return preg_replace(
            self::REGEX,
            '<attribute-message>user.dialogflow_message</attribute-message>',
            $messageMarkup
        );
    }
}
