<?php

namespace Tests\Feature\Components;

use OpenDialogAi\ActionEngine\Actions\WebhookAction;

class TestAction extends WebhookAction
{

    protected static array $hidden = ['access_token'];

    protected static string $configurationClass = TestConfiguration::class;

    public static function getConfigurationClass(): string
    {
        return self::$configurationClass;
    }

    public static function getHiddenFields(): array
    {
        return self::$hidden;
    }
}