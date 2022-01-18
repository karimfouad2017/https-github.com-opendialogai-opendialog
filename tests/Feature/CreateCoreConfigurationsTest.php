<?php


namespace Tests\Feature;


use App\Console\Commands\ConfigurationUpdates\AllUpdatesHaveRunException;
use App\Console\Commands\ConfigurationUpdates\Updates\CreateWebchatPlatforms;
use App\Console\Commands\CreateCoreConfigurations;
use Illuminate\Support\Collection;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Components\Configuration\ComponentConfigurationKey;
use OpenDialogAi\Core\Components\Configuration\ConfigurationDataHelper;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Facades\ConversationObjectDataClient;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\Conversation\ScenarioCollection;
use OpenDialogAi\Core\InterpreterEngine\OpenDialog\OpenDialogInterpreterConfiguration;
use OpenDialogAi\Core\InterpreterEngine\Service\ConfiguredInterpreterServiceInterface;
use OpenDialogAi\InterpreterEngine\Interpreters\OpenDialogInterpreter;
use OpenDialogAi\Core\ComponentSetting;
use Tests\TestCase;

class CreateCoreConfigurationsTest extends TestCase
{
    public function testSuccessCreatingWebchatPlatformsWithPreviousSettings()
    {
        $expectedSettings = $this->mockComponentSettings();

        $this->artisan('configurations:create up -u 3 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(0, ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
        ])->get());

        $this->artisan('configurations:create up -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        /** @var ComponentConfiguration[] $convertedSettings */
        $convertedSettings = ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
        ])->get();
        $this->assertCount(2, $convertedSettings);

        $this->assertEquals($expectedSettings, $convertedSettings[0]->configuration);
        $this->assertEquals($expectedSettings, $convertedSettings[1]->configuration);

        $this->artisan('configurations:create down -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(0, ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
        ])->get());
    }

    public function testSuccessCreatingWebchatPlatformsWithoutPreviousSettings()
    {
        ConversationObjectDataClient::shouldReceive('updateAllConversationObjectInterpreters');

        $this->mockTwoScenarios(
            '0x000',
            ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
            '0x001',
            ConfigurationDataHelper::OPENDIALOG_INTERPRETER
        );

        $this->artisan('configurations:create up -u 3 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(0, ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
        ])->get());

        $url = "https://example.com";
        $this->artisan("configurations:create up -u 1 --non-interactive --url {$url}")
            ->assertExitCode(0)
            ->run();

        /** @var ComponentConfiguration[] $convertedSettings */
        $convertedSettings = ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
        ])->get();
        $this->assertCount(2, $convertedSettings);

        $defaultSettings = CreateWebchatPlatforms::getDefaultSettings($url);
        $this->assertEquals($defaultSettings, $convertedSettings[0]->configuration);
        $this->assertEquals($defaultSettings, $convertedSettings[1]->configuration);

        $this->artisan('configurations:create down -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(0, ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
        ])->get());
    }

    public function testSuccessWithConfigurationScenarioScopingUpdate()
    {
        $uid1 = '0x000';
        $interpreter1 = ConfigurationDataHelper::OPENDIALOG_INTERPRETER;
        $uid2 = '0x001';
        $interpreter2 = 'other';

        $this->mockTwoScenarios($uid1, $interpreter1, $uid2, $interpreter2);

        ComponentConfiguration::create([
            'name' => $interpreter1,
            'scenario_id' => '',
            'component_id' => OpenDialogInterpreter::getComponentId(),
            'configuration' => [
                OpenDialogInterpreterConfiguration::ENABLE_SIMILARITY_EVALUATION => true,
                OpenDialogInterpreterConfiguration::CALLBACKS => [
                    'WELCOME' => 'intent.core.welcome',
                ],
            ],
            'active' => true,
        ]);

        ComponentConfiguration::create([
            'name' => $interpreter2,
            'scenario_id' => '',
            'component_id' => OpenDialogInterpreter::getComponentId(),
            'configuration' => [
                OpenDialogInterpreterConfiguration::ENABLE_SIMILARITY_EVALUATION => false,
                OpenDialogInterpreterConfiguration::CALLBACKS => [
                    'WELCOME' => 'intent.core.hello',
                ],
            ],
            'active' => true,
        ]);

        $this->artisan('configurations:create -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(4, ComponentConfiguration::all());

        $service = resolve(ConfiguredInterpreterServiceInterface::class);
        $this->assertTrue($service->has(new ComponentConfigurationKey($uid1, $interpreter1)));
        $this->assertTrue($service->has(new ComponentConfigurationKey($uid1, $interpreter2)));
        $this->assertTrue($service->has(new ComponentConfigurationKey($uid2, $interpreter1)));
        $this->assertTrue($service->has(new ComponentConfigurationKey($uid2, $interpreter2)));

        $this->artisan('configurations:create down -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(2, ComponentConfiguration::all());
    }

    public function testSuccessWithCallbackToOpenDialogInterpreterUpdate()
    {
        $config = [
            OpenDialogInterpreterConfiguration::CALLBACKS => [
                'hello' => 'world',
            ]
        ];

        /** @var ComponentConfiguration $original */
        $original = ComponentConfiguration::create([
            'name' => 'Default Callback',
            'component_id' => 'interpreter.core.callbackInterpreter',
            'configuration' => $config,
            'active' => true,
        ]);

        ConversationObjectDataClient::shouldReceive('updateAllConversationObjectInterpreters')
            ->twice();

        $this->artisan('configurations:create up -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(1, ComponentConfiguration::all());

        $name = ConfigurationDataHelper::OPENDIALOG_INTERPRETER;

        /** @var Collection|ComponentConfiguration[] $configurations */
        $configurations = ComponentConfiguration::where(['name' => $name])->get();

        $this->assertEquals($name, $configurations[0]->name);
        $this->assertEquals('', $configurations[0]->scenario_id);
        $this->assertEquals(OpenDialogInterpreter::getComponentId(), $configurations[0]->component_id);
        $this->assertEquals(
            $config + [OpenDialogInterpreterConfiguration::ENABLE_SIMILARITY_EVALUATION => true],
            $configurations[0]->configuration
        );
        $this->assertTrue($configurations[0]->active);

        $this->artisan('configurations:create down -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(1, ComponentConfiguration::all());

        $name = ConfigurationDataHelper::DEFAULT_CALLBACK;

        /** @var Collection|ComponentConfiguration[] $configurations */
        $configurations = ComponentConfiguration::where(['name' => $name])->get();

        $this->assertEquals($original->name, $configurations[0]->name);
        $this->assertEquals($original->scenario_id, $configurations[0]->scenario_id);
        $this->assertEquals($original->component_id, $configurations[0]->component_id);
        $this->assertArrayHasKey('hello', $configurations[0]->configuration['callbacks']);
        $this->assertEquals($original->active, $configurations[0]->active);
    }

    public function testSuccessCreateCallbackInterpreter()
    {
        $this->artisan('configurations:create up -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(1, ComponentConfiguration::all());

        $this->assertNotNull(ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::DEFAULT_CALLBACK,
            'scenario_id' => '',
        ])->first());

        $this->artisan('configurations:create down -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(0, ComponentConfiguration::all());

        $this->assertNull(ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::DEFAULT_CALLBACK,
            'scenario_id' => '',
        ])->first());
    }

    public function testAddTypingIndicatorOnSend()
    {
        $this->artisan('configurations:create up -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(1, ComponentConfiguration::all());

        $this->assertNotNull(ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::DEFAULT_CALLBACK,
            'scenario_id' => '',
        ])->first());

        $this->artisan('configurations:create down -u 1 --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->assertCount(0, ComponentConfiguration::all());

        $this->assertNull(ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::DEFAULT_CALLBACK,
            'scenario_id' => '',
        ])->first());
    }

    public function testNothingToRun()
    {
        $this->mockComponentSettings();

        $this->artisan('configurations:create up --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->artisan('configurations:create up --non-interactive')
            ->assertExitCode(0)
            ->expectsOutput('There are no further updates to be run.')
            ->run();

        $this->artisan('configurations:create down --non-interactive')
            ->assertExitCode(0)
            ->run();

        $this->artisan('configurations:create down --non-interactive')
            ->assertExitCode(0)
            ->expectsOutput('There are no further updates to be run.')
            ->run();
    }

    /**
     * @param string $uid1
     * @param string $interpreter1
     * @param string $uid2
     * @param string $interpreter2
     */
    public function mockTwoScenarios(string $uid1, string $interpreter1, string $uid2, string $interpreter2): void
    {
        $scenario = new Scenario();
        $scenario->setUid($uid1);
        $scenario->setOdId('test');
        $scenario->setName('test');
        $scenario->setInterpreter($interpreter1);

        $scenario2 = new Scenario();
        $scenario2->setUid($uid2);
        $scenario2->setOdId('test2');
        $scenario2->setName('test2');
        $scenario2->setInterpreter($interpreter2);

        ConversationDataClient::shouldReceive('getAllScenarios')
            ->andReturn(new ScenarioCollection([$scenario, $scenario2]));
    }

    public function testDetermineIndices()
    {
        // All up after running the first update
        list($start, $end) = CreateCoreConfigurations::determineStartAndEndIndices('up', 0, -1, 5);
        $this->assertEquals(1, $start);
        $this->assertEquals(5, $end);

        // All down after running the first update
        list($start, $end) = CreateCoreConfigurations::determineStartAndEndIndices('down', 4, -1, 5);
        $this->assertEquals(4, $start);
        $this->assertEquals(5, $end);

        // Two up after running the first update
        list($start, $end) = CreateCoreConfigurations::determineStartAndEndIndices('up', 0, 2, 5);
        $this->assertEquals(1, $start);
        $this->assertEquals(3, $end);

        // Two down after running the fourth update
        list($start, $end) = CreateCoreConfigurations::determineStartAndEndIndices('down', 1, 2, 5);
        $this->assertEquals(1, $start);
        $this->assertEquals(3, $end);

        // Two up after running the fourth update (ie. there's only one left to run up)
        list($start, $end) = CreateCoreConfigurations::determineStartAndEndIndices('up', 3, 2, 5);
        $this->assertEquals(4, $start);
        $this->assertEquals(5, $end);

        // Two down after running the first update (ie. there's only one left to run down)
        list($start, $end) = CreateCoreConfigurations::determineStartAndEndIndices('down', 4, 2, 5);
        $this->assertEquals(4, $start);
        $this->assertEquals(5, $end);
    }

    /**
     * Tests that an exception is thrown if the previously run update was the last one to be run up
     *
     * @throws AllUpdatesHaveRunException
     */
    public function testDetermineIndicesWhenAllHaveRunUp()
    {
        $this->expectException(AllUpdatesHaveRunException::class);
        CreateCoreConfigurations::determineStartAndEndIndices('up', 4, -1, 5);
    }


    /**
     * Tests that an exception is thrown if the previously run update was the last one to be run down
     *
     * @throws AllUpdatesHaveRunException
     */
    public function testDetermineIndicesWhenAllHaveRunDown()
    {
        $this->expectException(AllUpdatesHaveRunException::class);
        CreateCoreConfigurations::determineStartAndEndIndices('down', -1, -1, 5);
    }

    /**
     * @return array[]
     */
    public function mockComponentSettings(): array
    {
        // Create old component settings to be converted
        $generalSetting = new ComponentSetting();
        $generalSetting->name = 'general';
        $generalSetting->value = 'general';
        $generalSetting->type = 'object';
        $generalSetting->component_id = 'platform.core.webchat';
        $generalSetting->save();

        $setting = new ComponentSetting();
        $setting->name = 'teamName';
        $setting->value = 'OpenDialog Webchat';
        $setting->type = 'string';
        $setting->parent_id = $generalSetting->id;
        $setting->component_id = 'platform.core.webchat';
        $setting->save();

        $setting2 = new ComponentSetting();
        $setting2->name = 'open';
        $setting2->value = true;
        $setting2->type = 'boolean';
        $setting2->parent_id = $generalSetting->id;
        $setting2->component_id = 'platform.core.webchat';
        $setting2->save();

        $expectedSettings = [
            'general' => [
                'teamName' => 'OpenDialog Webchat',
                'open' => true,
            ]
        ];

        ConversationObjectDataClient::shouldReceive('updateAllConversationObjectInterpreters');

        $this->mockTwoScenarios(
            '0x000',
            ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
            '0x001',
            ConfigurationDataHelper::OPENDIALOG_INTERPRETER
        );
        return $expectedSettings;
    }
}
