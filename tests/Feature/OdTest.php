<?php

namespace Tests\Feature;

use App\User;
use OpenDialogAi\ActionEngine\Service\ActionServiceInterface;
use OpenDialogAi\AttributeEngine\AttributeResolver\AttributeResolver;
use OpenDialogAi\ContextEngine\Contracts\ContextService;
use OpenDialogAi\Core\InterpreterEngine\Service\InterpreterServiceInterface;
use OpenDialogAi\InterpreterEngine\Service\InterpreterComponentServiceInterface;
use OpenDialogAi\ResponseEngine\Service\FormatterServiceInterface;
use OpenDialogAi\SensorEngine\Service\SensorServiceInterface;
use OpenDialogAi\Core\Console\Commands\ComponentSettings;
use OpenDialogAi\Webchat\WebchatSetting;
use Tests\TestCase;

class OdTest extends TestCase
{
    /**
     * Verify that the demo endpoint is present.
     *
     * @return void
     */
    public function testDemoEndpoint()
    {
        $user = factory(User::class)->create();

        $response = $this->actingAs($user)->get('/admin/demo');

        $response->assertStatus(200);
    }

    /**
     * Verify that the webchat endpoint is present.
     *
     * @return void
     */
    public function testWebchatEndpoint()
    {
        $response = $this->get('/web-chat');

        $response->assertStatus(404);
    }

    /**
     * Verify that the webchat settings endpoint is present.
     *
     * @return void
     */
    public function testWebchatSettingsEndpoint()
    {
        $response = $this->get('/webchat-config?scenario_id=0x000');

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    /**
     * Verify that the OD-Core service providers are available.
     *
     * @return void
     */
    public function testOdCoreServiceProviders()
    {
        $actionEngine = resolve(ActionServiceInterface::class);
        $this->assertInstanceOf(ActionServiceInterface::class, $actionEngine);

        $contextService = resolve(ContextService::class);
        $this->assertInstanceOf(ContextService::class, $contextService);

        $attributeResolver = resolve(AttributeResolver::class);
        $this->assertInstanceOf(AttributeResolver::class, $attributeResolver);

        $interpreterService = resolve(InterpreterServiceInterface::class);
        $this->assertInstanceOf(InterpreterServiceInterface::class, $interpreterService);

        $responseEngineService = resolve(FormatterServiceInterface::class);
        $this->assertInstanceOf(FormatterServiceInterface::class, $responseEngineService);

        $sensorService = resolve(SensorServiceInterface::class);
        $this->assertInstanceOf(SensorServiceInterface::class, $sensorService);
    }

    /**
     * Verify that the OD-Webchat service provider is available.
     *
     * @return void
     */
    public function testOdWebchatServiceProvider()
    {
        $webChatSettings = app('OpenDialogAi\Core\Console\Commands\ComponentSettings');
        $this->assertInstanceOf(ComponentSettings::class, $webChatSettings);
    }
}
