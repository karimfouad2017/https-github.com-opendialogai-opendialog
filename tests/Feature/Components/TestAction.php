<?php

namespace Tests\Feature\Components;

use OpenDialogAi\ActionEngine\Actions\WebhookAction;

class TestAction extends WebhookAction
{
    protected static string $configurationClass = TestConfiguration::class;

    public static function getConfigurationClass(): string
    {
        return self::$configurationClass;
    }
}