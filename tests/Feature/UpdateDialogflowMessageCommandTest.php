<?php

namespace Tests\Feature;

use OpenDialogAi\Core\Conversation\Facades\MessageTemplateDataClient;
use OpenDialogAi\Core\Conversation\MessageTemplate;
use OpenDialogAi\Core\Conversation\MessageTemplateCollection;
use Tests\TestCase;

class UpdateDialogflowMessageCommandTest extends TestCase
{
    public function testUpdateNoValidMessagesMessages()
    {
        $markupOne = "<message><attribute-message>user.dialogflow_message</attribute-message></message>";
        $markupTwo = "<message><text-message>{user.message}</text-message></message>";

        $message1 = new MessageTemplate();
        $message1->setMessageMarkup($markupOne);

        $message2 = new MessageTemplate();
        $message2->setMessageMarkup($markupTwo);

        MessageTemplateDataClient::shouldReceive('getAllMessageTemplates')
            ->once()
            ->andReturn(new MessageTemplateCollection([$message1, $message2]));

        MessageTemplateDataClient::shouldReceive('updateMessageTemplate')->never();

        \Artisan::call('attribute_message:update');
    }

    public function testUpdateSingleValidMessagesMessages()
    {
        $markupOne = "<message><attribute-message>user.dialogflow_message</attribute-message></message>";
        $markupTwo = "<message><text-message>{user.message}</text-message></message>";
        $markupThree = "<message>{user.dialogflow_message}</message>";

        $message1 = new MessageTemplate();
        $message1->setMessageMarkup($markupOne);

        $message2 = new MessageTemplate();
        $message2->setMessageMarkup($markupTwo);

        $message3 = new MessageTemplate();
        $message3->setMessageMarkup($markupThree);

        MessageTemplateDataClient::shouldReceive('getAllMessageTemplates')
            ->once()
            ->andReturn(new MessageTemplateCollection([$message1, $message2, $message3]));

        MessageTemplateDataClient::shouldReceive('updateMessageTemplate')->once();

        \Artisan::call('attribute_message:update');
    }

    public function testUpdateMultipleValidMessagesMessages()
    {
        $markupOne = "<message><text-message>Before</text-message>{   user.dialogflow_message}</message>";
        $markupTwo = "<message>{user.dialogflow_message  }<text-message>After</text-message></message>";
        $markupThree =
            "<message><text-message>Before</text-message>{ user.dialogflow_message }<text-message>After</text-message></message>";

        $message1 = new MessageTemplate();
        $message1->setMessageMarkup($markupOne);

        $message2 = new MessageTemplate();
        $message2->setMessageMarkup($markupTwo);

        $message3 = new MessageTemplate();
        $message3->setMessageMarkup($markupThree);

        MessageTemplateDataClient::shouldReceive('getAllMessageTemplates')
            ->once()
            ->andReturn(new MessageTemplateCollection([$message1, $message2, $message3]));

        MessageTemplateDataClient::shouldReceive('updateMessageTemplate')->times(3);

        \Artisan::call('attribute_message:update');
    }

    public function testUpdateCheckContentOfUpdate()
    {
        $markup =
            "<message><text-message>Before</text-message>{ user.dialogflow_message   }<text-message>After</text-message></message>";

        $updatedMarkUp =
            "<message><text-message>Before</text-message><attribute-message>user.dialogflow_message</attribute-message><text-message>After</text-message></message>";

        $message = new MessageTemplate();
        $message->setMessageMarkup($markup);

        MessageTemplateDataClient::shouldReceive('getAllMessageTemplates')
            ->once()
            ->andReturn(new MessageTemplateCollection([$message]));

        MessageTemplateDataClient::shouldReceive('updateMessageTemplate')
            ->once()
            ->with(\Mockery::on(function (MessageTemplate $messageTemplate) use ($updatedMarkUp) {
                return $messageTemplate->getMessageMarkup() == $updatedMarkUp;
            }));

        \Artisan::call('attribute_message:update');
    }
}