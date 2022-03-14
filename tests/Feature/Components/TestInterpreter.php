<?php

namespace Tests\Feature\Components;

use OpenDialogAi\InterpreterEngine\Interpreters\OpenDialogInterpreter;

class TestInterpreter extends OpenDialogInterpreter
{
    protected static string $componentId = 'interpreter.core.customInterpreter';

    protected static ?string $componentName = 'Custom';

    /**
     * @inheritDoc
     */
    public static function getConfigurationRules(): array
    {
        return [
            "configuration" => ""
        ];
    }
}
