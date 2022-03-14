<?php

namespace Tests\Feature;

use App\User;
use DateTime;
use Mockery\MockInterface;
use OpenDialogAi\ActionEngine\Configuration\ActionConfiguration;
use OpenDialogAi\ActionEngine\Actions\WebhookAction;
use OpenDialogAi\ActionEngine\Service\ActionComponentServiceInterface;
use OpenDialogAi\AttributeEngine\CoreAttributes\UtteranceAttribute;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Components\Configuration\ConfigurationDataHelper;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\InterpretedIntentCollection;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\Conversation\ScenarioCollection;
use OpenDialogAi\Core\InterpreterEngine\Luis\LuisInterpreterConfiguration;
use OpenDialogAi\Core\InterpreterEngine\OpenDialog\OpenDialogInterpreterConfiguration;
use OpenDialogAi\InterpreterEngine\Interpreters\CallbackInterpreter;
use OpenDialogAi\InterpreterEngine\Interpreters\OpenDialogInterpreter;
use OpenDialogAi\InterpreterEngine\Service\InterpreterComponentServiceInterface;
use Tests\Feature\Components\TestAction;
use Tests\Feature\Components\TestInterpreter;
use Tests\TestCase;

class ComponentConfigurationTest extends TestCase
{
    const COMPONENT_ID = 'interpreter.core.callbackInterpreter';
    const CONFIGURATION = [
        'callbacks' => [
            'WELCOME' => 'intent.core.welcome',
        ],
    ];

    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
    }

    public function testView()
    {
        /** @var ComponentConfiguration $configuration */
        $configuration = factory(ComponentConfiguration::class)->create();

        $this->get('/admin/api/component-configuration/'.$configuration->id)
            ->assertStatus(302);

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-configuration/'.$configuration->id)
            ->assertStatus(200)
            ->assertJsonFragment([
                'component_id' => self::COMPONENT_ID,
                'configuration' => self::CONFIGURATION
            ]);
    }

    public function testViewAll()
    {
        for ($i = 0; $i < 51; $i++) {
            factory(ComponentConfiguration::class)->create();
        }

        $configurations = ComponentConfiguration::all();

        $this->get('/admin/api/component-configuration')
            ->assertStatus(302);

        $response = $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-configuration?page=1')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    $configurations[0]->toArray(),
                    $configurations[1]->toArray(),
                    $configurations[2]->toArray(),
                ],
            ])
            ->getData();

        $this->assertEquals(50, count($response->data));

        $response = $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-configuration?page=2')
            ->assertStatus(200)
            ->getData();

        $this->assertEquals(1, count($response->data));
    }

    public function testViewAllByComponentType()
    {
        factory(ComponentConfiguration::class)->create([
            'component_id' => 'interpreter.test.one',
        ]);

        factory(ComponentConfiguration::class)->create([
            'component_id' => 'action.test.one',
        ]);

        factory(ComponentConfiguration::class)->create([
            'component_id' => 'action.test.two',
        ]);

        $this->mock(ActionComponentServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->twice()
                ->andReturn(WebhookAction::class);
        });

        $configurations = ComponentConfiguration::all();

        $this->get('/admin/api/component-configuration?type=interpreter')
            ->assertStatus(302);

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-configuration?type=action')
            ->assertStatus(200)
            ->assertJson([
                'data' => [
                    $configurations[1]->toArray(),
                    $configurations[2]->toArray(),
                ],
            ]);
    }

    public function testUpdate()
    {
        /** @var ComponentConfiguration $configuration */
        $configuration = factory(ComponentConfiguration::class)->create();

        $data = [
            'name' => 'My New Name',
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('PATCH', '/admin/api/component-configuration/'.$configuration->id, $data)
            ->assertNoContent();

        /** @var ComponentConfiguration $updatedConfiguration */
        $updatedConfiguration = ComponentConfiguration::find($configuration->id);

        $this->assertEquals($data['name'], $updatedConfiguration->name);
        $this->assertEquals(self::COMPONENT_ID, $updatedConfiguration->component_id);
        $this->assertEquals(self::CONFIGURATION, $updatedConfiguration->configuration);
    }

    public function testUpdateDuplicateNameAndScenario()
    {
        /** @var ComponentConfiguration $a */
        $a = factory(ComponentConfiguration::class)->create();

        /** @var ComponentConfiguration $b */
        $b = factory(ComponentConfiguration::class)->create();

        $data = [
            'name' => $b->name
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->with($a->scenario_id)
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('PATCH', '/admin/api/component-configuration/'.$a->id, $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function testUpdateNameToNew()
    {
        /** @var ComponentConfiguration $a */
        $a = factory(ComponentConfiguration::class)->create();

        /** @var ComponentConfiguration $b */
        $b = factory(ComponentConfiguration::class)->create();

        $a->scenario_id = '0x000';
        $a->save();

        $b->scenario_id = '0x001';
        $b->save();

        $data = [
            'name' => $b->name
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->with($a->scenario_id)
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('PATCH', '/admin/api/component-configuration/'.$a->id, $data)
            ->assertStatus(204);
    }

    public function testUpdateNameToSame()
    {
        /** @var ComponentConfiguration $a */
        $a = factory(ComponentConfiguration::class)->create();

        /** @var ComponentConfiguration $b */
        $b = factory(ComponentConfiguration::class)->create();

        $a->scenario_id = '0x000';
        $a->save();

        $b->scenario_id = '0x001';
        $b->save();

        $data = [
            'name' => $a->name
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->with($a->scenario_id)
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('PATCH', '/admin/api/component-configuration/'.$a->id, $data)
            ->assertStatus(204);
    }

    public function testUpdateActive()
    {
        /** @var ComponentConfiguration $a */
        $a = factory(ComponentConfiguration::class)->create();

        $data = [
            'active' => false
        ];

        $this->actingAs($this->user, 'api')
            ->json('PATCH', '/admin/api/component-configuration/'.$a->id, $data)
            ->assertStatus(204);
    }

    public function testStoreValidData()
    {
        $name = 'My New Name';
        $scenarioId = '0x001';

        $data = [
            'name' => $name,
            'scenario_id' => $scenarioId,
            'component_id' => self::COMPONENT_ID,
            'configuration' => self::CONFIGURATION,
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration', $data)
            ->assertStatus(201)
            ->assertJsonFragment($data);

        $this->assertDatabaseHas('component_configurations', ['name' => $name, 'scenario_id' => $scenarioId]);
    }

    public function testStoreInvalidComponentId()
    {
        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => 'unknown',
            'configuration' => [],
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['component_id']);
    }

    public function testStoreMissingName()
    {
        $data = [
            'scenario_id' => '0x000',
            'component_id' => self::COMPONENT_ID,
            'configuration' => self::CONFIGURATION,
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function testStoreMissingScenarioId()
    {
        $data = [
            'name' => 'Test',
            'component_id' => self::COMPONENT_ID,
            'configuration' => self::CONFIGURATION,
        ];

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['scenario_id']);
    }

    public function testStoreInvalidConfiguration()
    {
        // LUIS interpreter requires three fields in its configuration (app_url, app_id, subscription_key)

        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => 'interpreter.core.luis',
            'configuration' => [
                LuisInterpreterConfiguration::APP_URL => 'https://example.com/',
                LuisInterpreterConfiguration::APP_ID => '123',
            ],
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['configuration']);
    }

    public function testStoreInvalidAppUrl()
    {
        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => 'interpreter.core.luis',
            'configuration' => [
                LuisInterpreterConfiguration::APP_URL => 'file://example.com/', //invalid scheme
                LuisInterpreterConfiguration::APP_ID => '123',
            ],
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['configuration.app_url']);
    }

    public function testStoreInvalidAppUrlLocalEnv()
    {
        $this->app['config']->set('app.env', 'local');

        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => 'interpreter.core.luis',
            'configuration' => [
                LuisInterpreterConfiguration::APP_URL => 'file://example.com/', //invalid scheme
                LuisInterpreterConfiguration::APP_ID => '123',
            ],
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertStatus(422)
            ->assertJsonMissingValidationErrors(['configuration.app_url']);
    }

    public function testStoreInvalidWebhookUrl()
    {
        $data = [
            'name' => 'Bad webhook',
            'scenario_id' => '0x000',
            'component_id' => 'action.core.webhook',
            'configuration' => [
                'webhook_url' => 'localhost'
            ],
        ];

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once();

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['configuration.webhook_url']);
    }

    public function testStoreInvalidWebhookUrlLocalEnv()
    {
        $this->app['config']->set('app.env', 'local');

        $data = [
            'name' => 'Bad webhook',
            'scenario_id' => '0x000',
            'component_id' => 'action.core.webhook',
            'configuration' => [
                'webhook_url' => 'localhost'
            ],
        ];

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configuration/', $data)
            ->assertJsonMissingValidationErrors(['configuration.webhook_url']);
    }

    public function testDestroy()
    {
        /** @var ComponentConfiguration $configuration */
        $configuration = factory(ComponentConfiguration::class)->create();

        $this->actingAs($this->user, 'api')
            ->json('DELETE', '/admin/api/component-configuration/'.$configuration->id)
            ->assertStatus(204);

        $this->assertEquals(null, ComponentConfiguration::find($configuration->id));
    }

    public function testTestConfigurationSuccess()
    {
        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => self::COMPONENT_ID,
            'configuration' => self::CONFIGURATION,
        ];

        $this->mock(InterpreterComponentServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->andReturn(CallbackInterpreter::class);

            // For request validation
            $mock->shouldReceive('has')
                ->once()
                ->andReturn(true);
        });

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/test', $data)
            ->assertStatus(200);
    }

    public function testTestConfigurationFailureInvalidData()
    {
        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'configuration' => self::CONFIGURATION,
        ];

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/test', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['component_id']);
    }

    public function testTestConfigurationFailureInvalidUrl()
    {
        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => 'interpreter.core.luis',
            'configuration' => [
                LuisInterpreterConfiguration::APP_URL => 'file://example.com/', //invalid scheme
                LuisInterpreterConfiguration::APP_ID => '123',
            ],
        ];

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/test', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['configuration.app_url']);
    }

    public function testTestConfigurationInvalidUrlLocalEnv()
    {
        $this->app['config']->set('app.env', 'local');

        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => 'interpreter.core.luis',
            'configuration' => [
                LuisInterpreterConfiguration::APP_URL => 'file://example.com/', //invalid scheme
                LuisInterpreterConfiguration::APP_ID => '123',
            ],
        ];

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/test', $data)
            ->assertJsonMissingValidationErrors(['configuration.app_url']);
    }

    public function testTestConfigurationFailureNoResponseFromProvider()
    {
        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => self::COMPONENT_ID,
            'configuration' => self::CONFIGURATION,
        ];

        $mockInterpreter = new class(OpenDialogInterpreterConfiguration::create('test', self::CONFIGURATION)) extends OpenDialogInterpreter {
            public function interpret(UtteranceAttribute $utterance): InterpretedIntentCollection
            {
                return new InterpretedIntentCollection();
            }
        };

        $this->mock(InterpreterComponentServiceInterface::class, function (MockInterface $mock) use ($mockInterpreter) {
            $mock->shouldReceive('get')
                ->andReturn(get_class($mockInterpreter));

            $mock->shouldReceive('has')
                ->once()
                ->andReturn(true);
        });

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/test', $data)
            ->assertStatus(400);
    }

    public function testQueryConfigurationUse()
    {
        /** @var ComponentConfiguration $configuration */
        $configuration = ComponentConfiguration::create([
            'name' => ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
            'scenario_id' => '0x123',
            'component_id' => OpenDialogInterpreter::getComponentId(),
            'configuration' => [],
            'active' => true,
        ]);

        $data = [
            'name' => $configuration->name,
            'scenario_id' => $configuration->scenario_id,
        ];

        $scenario1 = new Scenario();
        $scenario1->setUid($configuration->scenario_id);
        $scenario1->setOdId('scenario_1');
        $scenario1->setInterpreter($configuration->name);
        $scenario1->setCreatedAt(new DateTime());
        $scenario1->setUpdatedAt(new DateTime());

        $scenario2 = new Scenario();
        $scenario2->setUid('0x456');
        $scenario2->setOdId('scenario_2');
        $scenario2->setInterpreter($configuration->name);
        $scenario2->setCreatedAt(new DateTime());
        $scenario2->setUpdatedAt(new DateTime());

        ConversationDataClient::shouldReceive('getScenariosWhereInterpreterIsUsed')
            ->once()
            ->andReturn(new ScenarioCollection([
                $scenario1,
                $scenario2,
            ]));

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/' . $configuration->id . '/query')
            ->assertStatus(200);
    }

    public function testQueryConfigurationNotInUse()
    {
        /** @var ComponentConfiguration $configuration */
        $configuration = factory(ComponentConfiguration::class)->create();

        ConversationDataClient::shouldReceive('getScenariosWhereInterpreterIsUsed')
            ->once()
            ->andReturn(new ScenarioCollection([]));

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/' . $configuration->id . '/query')
            ->assertStatus(404);
    }

    public function testSingleConfigurationPropertyHiding()
    {
        $configuration = factory(ComponentConfiguration::class)->create([
            'component_id' => 'action.test.one',
            'configuration' => [
                'access_token' => 'abcd',
                'public' => true
            ]
        ]);

        $this->mock(ActionComponentServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->andReturn(TestAction::class);
        });

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-configuration/' . $configuration->id)
            ->assertStatus(200)
            ->assertJsonMissing([
                'access_token' => 'abcd',
            ]);
    }

    public function testCollectionConfigurationPropertyHiding()
    {
        factory(ComponentConfiguration::class)->create([
            'component_id' => 'action.test.one',
            'configuration' => [
                'public' => true,
                'private_key' => '123456'
            ]
        ]);

        factory(ComponentConfiguration::class)->create([
            'component_id' => 'action.test.two',
            'configuration' => [
                'access_token' => 'abcd',
                'public' => true,
            ]
        ]);

        $this->mock(ActionComponentServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->andReturn(TestAction::class);
        });

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-configuration?type=action')
            ->assertStatus(200)
            ->assertJsonMissing([
                'access_token' => 'abcd',
            ])
            ->assertJsonMissing([
                'private_key' => '123456'
            ]);
    }

    public function testConfigurationPropertyHidingWithDotNotation()
    {
        $configuration = factory(ComponentConfiguration::class)->create([
            'component_id' => 'action.test.one',
            'configuration' => [
                'public' => true,
                'private_key' => '123456',
                'general' => [
                    'user' => [
                        'access_token' => '12345',
                    ],
                    'private' => [
                        'key' => 'key-12345'
                    ]
                ]
            ]
        ]);

        $this->mock(ActionComponentServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->andReturn(TestAction::class);
        });

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-configuration/' . $configuration->id)
            ->assertStatus(200)
            ->assertJsonMissing([
                'general' => [
                    'user' => [
                        'token' => '12345',
                    ],
                ]
            ])
            ->assertJsonMissing([
                'general' => [
                    'private' => [
                        'key' => 'key-12345'
                    ]
                ]
            ]);
    }

    public function testCustomRulesThatCanOverwriteDefaultAreIgnored()
    {
        $data = [
            'name' => 'My New Name',
            'scenario_id' => '0x000',
            'component_id' => 'interpreter.core.customInterpreter',
            'configuration' => '',
        ];

        $this->mock(InterpreterComponentServiceInterface::class, function (MockInterface $mock) {
            $mock->shouldReceive('get')
                ->andReturn(TestInterpreter::class);

            // For request validation
            $mock->shouldReceive('has')
                ->once()
                ->andReturn(true);
        });

        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/component-configurations/test', $data)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['configuration']);
    }
}
