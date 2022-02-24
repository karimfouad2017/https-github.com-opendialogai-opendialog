<?php


namespace Tests\Feature;

use App\Console\Facades\ImportExportSerializer;
use App\Http\Facades\Serializer;
use App\Http\Resources\ScenarioResource;
use App\ImportExportHelpers\ScenarioImportExportHelper;
use App\Template;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Components\Configuration\ConfigurationDataHelper;
use OpenDialogAi\Core\Conversation\Condition;
use OpenDialogAi\Core\Conversation\ConditionCollection;
use OpenDialogAi\Core\Conversation\Conversation;
use OpenDialogAi\Core\Conversation\ConversationCollection;
use OpenDialogAi\Core\Conversation\Exceptions\ConversationObjectNotFoundException;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Facades\ScenarioDataClient;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Conversation\IntentCollection;
use OpenDialogAi\Core\Conversation\MessageTemplate;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\Conversation\ScenarioCollection;
use OpenDialogAi\Core\Conversation\Scene;
use OpenDialogAi\Core\Conversation\SceneCollection;
use OpenDialogAi\Core\Conversation\Turn;
use OpenDialogAi\Core\Conversation\TurnCollection;
use OpenDialogAi\Core\InterpreterEngine\OpenDialog\OpenDialogInterpreterConfiguration;
use OpenDialogAi\InterpreterEngine\Interpreters\OpenDialogInterpreter;
use OpenDialogAi\MessageBuilder\MessageMarkUpGenerator;
use OpenDialogAi\PlatformEngine\Components\WebchatPlatform;
use Tests\TestCase;

class ScenariosTest extends TestCase
{
    protected $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
    }

    public function testGetScenariosRequiresAuthentication()
    {
        $this->get('/admin/api/conversation-builder/scenarios')
            ->assertStatus(302);
    }

    public function testGetScenarios()
    {
        $fakeScenario1 = new Scenario();
        $fakeScenario1->setName("Example scenario");
        $fakeScenario1->setUid('0x0001');
        $fakeScenario1->setODId('example_scenario');

        $fakeScenario2 = new Scenario();
        $fakeScenario2->setName("Example scenario");
        $fakeScenario2->setUid('0x0001');
        $fakeScenario2->setODId('example_scenario');

        $fakeScenarioCollection = new ScenarioCollection();
        $fakeScenarioCollection->addObject($fakeScenario1);
        $fakeScenarioCollection->addObject($fakeScenario2);

        ConversationDataClient::shouldReceive('getAllScenarios')
            ->once()
            ->andReturn($fakeScenarioCollection);

        Serializer::shouldReceive('normalize')
            ->twice()
            ->andReturn(
                json_decode('{
                    "uid": "0x0001",
                    "odId": "example_scenario1",
                    "name": "Example scenario1",
                    "description": "An example scenario",
                    "updatedAt": "2021-02-25T14:30:00.000Z",
                    "createdAt": "2021-02-24T09:30:00.000Z",
                    "defaultInterpreter": "interpreter.core.nlp",
                    "behaviors": [],
                    "conditions": [],
                    "status": "PUBLISHED",
                    "conversations": ["0x0002"]
                }', true),
                json_decode('{
                    "uid": "0x0002",
                    "odId": "example_scenario2",
                    "name": "Example scenario2",
                    "description": "An example scenario",
                    "updatedAt": "2021-02-25T14:30:00.000Z",
                    "createdAt": "2021-02-24T09:30:00.000Z",
                    "defaultInterpreter": "interpreter.core.nlp",
                    "behaviors": [],
                    "conditions": [],
                    "status": "PUBLISHED",
                    "conversations": ["0x0002"]
                }', true)
            );


        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/conversation-builder/scenarios')
            ->assertStatus(200)
            ->assertJson([
                [
                    "uid"=> "0x0001",
                    "odId"=> "example_scenario1",
                    "name"=> "Example scenario1",
                    'labels' => [
                        'platform_components' => [WebchatPlatform::getComponentId()],
                        'platform_types' => ['text'],
                    ]
                ],
                [
                    "uid"=> "0x0002",
                    "odId"=> "example_scenario2",
                    "name"=> "Example scenario2",
                    'labels' => [
                        'platform_components' => [WebchatPlatform::getComponentId()],
                        'platform_types' => ['text'],
                    ]
                ]
            ]);
    }

    public function testGetScenarioNotFound()
    {
        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->with('test', false)
            ->andThrow(new ConversationObjectNotFoundException());

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/conversation-builder/scenarios/test')
            ->assertStatus(404);
    }

    public function testGetScenarioByUid()
    {
        $fakeScenario = self::getFakeScenario();

        Serializer::shouldReceive('normalize')
            ->once()
            ->with($fakeScenario, 'json', ScenarioResource::$fields)
            ->andReturn(json_decode('{
            "uid": "0x0001",
            "odId": "example_scenario",
            "name": "Example scenario",
            "description": "An example scenario",
            "updatedAt": "2021-02-25T14:30:00.000Z",
            "createdAt": "2021-02-24T09:30:00.000Z",
            "defaultInterpreter": "interpreter.core.nlp",
            "behaviors": [],
            "conditions": [],
            "status": "PUBLISHED",
            "conversations": ["0x0002"]
        }', true));

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->with($fakeScenario->getUid(), false)
            ->andReturn($fakeScenario);

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/conversation-builder/scenarios/' . $fakeScenario->getUid())
            ->assertJson([
                'name' => 'Example scenario',
                'uid' => '0x0001',
                'odId' => 'example_scenario',
                'description' =>  'An example scenario',
                'labels' => [
                    'platform_components' => [WebchatPlatform::getComponentId()],
                    'platform_types' => ['text'],
                ]
            ]);
    }

    public function testCreateInvalidScenario()
    {
        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/conversation-builder/scenarios/', [
                'status' => 'not valid',
            ])
            ->assertStatus(422);
    }

    public function testCreateNewScenarioUsingApiDefaults()
    {
        $this->mockAndAssertScenarioCreation(function ($scenarioOdId, $scenarioUid, $conversationUid) {
            $this->actingAs($this->user, 'api')
                ->json('POST', '/admin/api/conversation-builder/scenarios/', [
                    'name' => 'Example scenario',
                    'od_id' => $scenarioOdId,
                    'description' =>  'An example scenario'
                ])
                ->assertStatus(201)
                ->assertJson([
                    'name' => 'Example scenario',
                    'id'=> $scenarioUid,
                    'od_id' => $scenarioOdId,
                    'description' =>  'An example scenario',
                    'conversations' => [['id' => $conversationUid]],
                    'labels' => [
                        'platform_components' => [WebchatPlatform::getComponentId()],
                        'platform_types' => ['text'],
                    ]
                ]);
        });
    }

    public function testCreateNewScenario()
    {
        $this->mockAndAssertScenarioCreation(function ($scenarioOdId, $scenarioUid, $conversationUid) {
            $this->actingAs($this->user, 'api')
                ->json('POST', '/admin/api/conversation-builder/scenarios?creation_type=default&object_id=platform.core.webchat', [
                    'name' => 'Example scenario',
                    'od_id' => $scenarioOdId,
                    'description' =>  'An example scenario'
                ])
                ->assertStatus(201)
                ->assertJson([
                    'name' => 'Example scenario',
                    'id'=> $scenarioUid,
                    'od_id' => $scenarioOdId,
                    'description' =>  'An example scenario',
                    'conversations' => [['id' => $conversationUid]],
                    'labels' => [
                        'platform_components' => [WebchatPlatform::getComponentId()],
                        'platform_types' => ['text'],
                    ]
                ]);
        });
    }

    public function testDuplicateScenarioFailure()
    {
        $scenario = self::getFakeScenarioForDuplication();

        // Called during route binding
        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->andReturn($scenario);

        // Called during OD ID rule
        ConversationDataClient::shouldReceive('getAllScenarios')
            ->once()
            ->andReturn(new ScenarioCollection([$scenario]));

        // Attempt to duplicate with same ID
        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/conversation-builder/scenarios/' . $scenario->getUid() . '/duplicate', [
                'name' => $scenario->getName(),
                'od_id' => $scenario->getODId(),
            ])
            ->assertStatus(422);
    }

    public function testCreateScenarioFromTemplateUsingDeprecatedEndpoint()
    {
        $this->mockAndAssertScenarioCreationFromTemplate(function ($templateId, $persistedScenarioId) {
            // Attempt to create from template with different od id
            $this->actingAs($this->user, 'api')
                ->json(
                    'POST',
                    '/admin/api/conversation-builder/scenarios/create-from-template/' . $templateId,
                    [
                        'name' => 'My scenario',
                        'od_id' => 'my_scenario'
                    ]
                )
                ->assertStatus(200)
                ->assertJson([
                    'name' => 'My scenario',
                    'od_id' => 'my_scenario',
                    'id'=> $persistedScenarioId,
                    "conditions" => [
                        [
                            "operation" => "eq",
                            "operationAttributes" => [
                                [
                                    "id" => "attribute",
                                    "value" => "user.selected_scenario"
                                ]
                            ],
                            "parameters" => [
                                [
                                    "id" => "value",
                                    "value" => $persistedScenarioId
                                ]
                            ]
                        ]
                    ]
                ]);
        });
    }

    public function testCreateScenarioFromTemplate()
    {
        $this->mockAndAssertScenarioCreationFromTemplate(function ($templateId, $persistedScenarioId) {
            // Attempt to create from template with different od id
            $this->actingAs($this->user, 'api')
                ->json(
                    'POST',
                    "/admin/api/conversation-builder/scenarios?creation_type=from-template&object_id=$templateId",
                    [
                        'name' => 'My scenario',
                        'od_id' => 'my_scenario'
                    ]
                )
                ->assertStatus(201)
                ->assertJson([
                    'name' => 'My scenario',
                    'od_id' => 'my_scenario',
                    'id'=> $persistedScenarioId,
                    "conditions" => [
                        [
                            "operation" => "eq",
                            "operationAttributes" => [
                                [
                                    "id" => "attribute",
                                    "value" => "user.selected_scenario"
                                ]
                            ],
                            "parameters" => [
                                [
                                    "id" => "value",
                                    "value" => $persistedScenarioId
                                ]
                            ]
                        ]
                    ]
                ]);
        });
    }

    public function testDuplicateScenarioSuccessUsingDeprecatedEndpoint()
    {
        $this->mockAndAssertScenarioDuplication(function ($uid) {
            // Attempt to duplicate with different ID
            $this->actingAs($this->user, 'api')
                ->json('POST', '/admin/api/conversation-builder/scenarios/' . $uid . '/duplicate')
                ->assertStatus(200)
                ->assertJson([
                    'name' => 'Example scenario copy',
                    'od_id' => 'example_scenario_copy',
                    'id'=> '0x9999',
                    "conditions" => [
                        [
                            "operation" => "eq",
                            "operationAttributes" => [
                                [
                                    "id" => "attribute",
                                    "value" => "user.selected_scenario"
                                ]
                            ],
                            "parameters" => [
                                [
                                    "id" => "value",
                                    "value" => "0x9999"
                                ]
                            ]
                        ]
                    ]
                ]);
        });
    }

    public function testDuplicateScenarioSuccess()
    {
        $this->mockAndAssertScenarioDuplication(function ($uid) {
            // Attempt to duplicate with different ID
            $this->actingAs($this->user, 'api')
                ->json(
                    'POST',
                    "/admin/api/conversation-builder/scenarios?creation_type=duplicate&object_id=$uid",
                )
                ->assertStatus(201)
                ->assertJson([
                    'name' => 'Example scenario copy',
                    'od_id' => 'example_scenario_copy',
                    'id'=> '0x9999',
                    "conditions" => [
                        [
                            "operation" => "eq",
                            "operationAttributes" => [
                                [
                                    "id" => "attribute",
                                    "value" => "user.selected_scenario"
                                ]
                            ],
                            "parameters" => [
                                [
                                    "id" => "value",
                                    "value" => "0x9999"
                                ]
                            ]
                        ]
                    ]
                ]);
        });
    }

    public function testUpdateScenarioNotFound()
    {
        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->with('test', false)
            ->andThrow(new ConversationObjectNotFoundException());

        $this->actingAs($this->user, 'api')
            ->json('PUT', '/admin/api/conversation-builder/scenarios/test')
            ->assertStatus(404);
    }

    public function testUpdateScenario()
    {
        $fakeScenario = self::getFakeScenario();
        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->with($fakeScenario->getUid(), false)
            ->andReturn($fakeScenario);

        $fakeScenarioUpdated = new Scenario();
        $fakeScenarioUpdated->setName("Example scenario updated");
        $fakeScenarioUpdated->setUid("0x0001");
        $fakeScenarioUpdated->setODId("example_scenario");
        $fakeScenarioUpdated->setDescription('An example scenario updated');

        Serializer::shouldReceive('deserialize')
            ->once()
            ->andReturn($fakeScenarioUpdated);

        Serializer::shouldReceive('normalize')
            ->once()
            ->with($fakeScenarioUpdated, 'json', ScenarioResource::$fields)
            ->andReturn(json_decode('{
            "uid": "0x0001",
            "odId": "example_scenario",
            "name": "Example scenario updated",
            "description": "An example scenario updated"
        }', true));

        ConversationDataClient::shouldReceive('updateScenario')
            ->once()
            ->with($fakeScenarioUpdated)
            ->andReturn($fakeScenarioUpdated);

        $this->actingAs($this->user, 'api')
            ->json('PUT', '/admin/api/conversation-builder/scenarios/' . $fakeScenarioUpdated->getUid(), [
                'name' => $fakeScenarioUpdated->getName(),
                'uid' => $fakeScenarioUpdated->getUid(),
                'odId' => $fakeScenarioUpdated->getODId(),
                'description' =>  $fakeScenarioUpdated->getDescription()
            ])
            ->assertStatus(200)
            ->assertJson([
                'name' => 'Example scenario updated',
                'uid'=> '0x0001',
                'odId' => 'example_scenario',
                'description' =>  'An example scenario updated'
            ]);
    }

    public function deleteScenarioNotFound()
    {
        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->with('test', false)
            ->andReturn(null);

        $this->actingAs($this->user, 'api')
            ->json('DELETE', '/admin/api/conversation-builder/scenarios/test')
            ->assertStatus(404);
    }

    public function testDeleteScenario()
    {
        $fakeScenario = self::getFakeScenario();

        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->with($fakeScenario->getUid(), false)
            ->andReturn($fakeScenario);

        ConversationDataClient::shouldReceive('deleteScenarioByUid')
            ->once()
            ->with($fakeScenario->getUid())
            ->andReturn(true);

        $this->actingAs($this->user, 'api')
            ->json('DELETE', '/admin/api/conversation-builder/scenarios/' . $fakeScenario->getUid())
            ->assertStatus(200);
    }

    /**
     * @return Scenario
     */
    public static function getFakeScenario(): Scenario
    {
        $fakeScenario = new Scenario();
        $fakeScenario->setName("Example scenario");
        $fakeScenario->setUid('0x0001');
        $fakeScenario->setODId('example_scenario');
        $fakeScenario->setConditions(new ConditionCollection([
            new Condition(
                'eq',
                ['attribute' => 'user.selected_scenario'],
                ['value' => '0x0001'],
            )
        ]));
        $fakeScenario->setCreatedAt(Carbon::now());
        $fakeScenario->setUpdatedAt(Carbon::now());

        return $fakeScenario;
    }

    public static function getFakeScenarioForDuplication(): Scenario
    {
        $scenario = self::getFakeScenario();

        $conversation = new Conversation();
        $conversation->setName("Example Conversation");
        $conversation->setUid('0x0002');
        $conversation->setOdId("example_conversation");
        $conversation->setCreatedAt(Carbon::now());
        $conversation->setUpdatedAt(Carbon::now());
        $conversation->setScenario($scenario);
        $conversations[] = $conversation;

        $scene = new Scene();
        $scene->setName("Example Scene");
        $scene->setUid('0x0003');
        $scene->setOdId("example_scene");
        $scene->setCreatedAt(Carbon::now());
        $scene->setUpdatedAt(Carbon::now());
        $scene->setConversation($conversations[0]);
        $scenes[] = $scene;

        $turn = new Turn();
        $turn->setName("Example Turn");
        $turn->setUid('0x0004');
        $turn->setOdId("example_turn");
        $turn->setCreatedAt(Carbon::now());
        $turn->setUpdatedAt(Carbon::now());
        $turn->setScene($scenes[0]);
        $turns[] = $turn;

        $requestIntent = new Intent();
        $requestIntent->setSpeaker(Intent::USER);
        $requestIntent->setIsRequestIntent(true);
        $requestIntent->setName("Example Request Intent");
        $requestIntent->setUid('0x0005');
        $requestIntent->setOdId("intent.app.exampleRequestIntent");
        $requestIntent->setCreatedAt(Carbon::now());
        $requestIntent->setUpdatedAt(Carbon::now());
        $requestIntent->setTurn($turns[0]);
        $requestIntents[] = $requestIntent;

        $responseIntent = new Intent();
        $responseIntent->setSpeaker(Intent::APP);
        $responseIntent->setIsRequestIntent(false);
        $responseIntent->setName("Example Response Intent");
        $responseIntent->setUid('0x0006');
        $responseIntent->setOdId("intent.app.exampleResponseIntent");
        $responseIntent->setCreatedAt(Carbon::now());
        $responseIntent->setUpdatedAt(Carbon::now());
        $responseIntent->setTurn($turns[0]);
        $responseIntents[] = $responseIntent;

        $message = new MessageTemplate();
        $message->setName("Example Message");
        $message->setUid('0x0007');
        $message->setOdId('example_message');
        $message->setMessageMarkup((new MessageMarkUpGenerator())->addTextMessage('Hello world')->getMarkUp());
        $message->setCreatedAt(Carbon::now());
        $message->setUpdatedAt(Carbon::now());
        $message->setIntent($responseIntent);

        $conversation = new Conversation();
        $conversation->setName("Example Conversation copy");
        $conversation->setUid('0x0002');
        $conversation->setOdId("example_conversation_copy");
        $conversation->setCreatedAt(Carbon::now());
        $conversation->setUpdatedAt(Carbon::now());
        $conversation->setScenario($scenario);
        $conversations[] = $conversation;

        $scene = new Scene();
        $scene->setName("Example Scene copy");
        $scene->setUid('0x0003');
        $scene->setOdId("example_scene_copy");
        $scene->setCreatedAt(Carbon::now());
        $scene->setUpdatedAt(Carbon::now());
        $scene->setConversation($conversations[0]);
        $scenes[] = $scene;

        $turn = new Turn();
        $turn->setName("Example Turn copy");
        $turn->setUid('0x0004');
        $turn->setOdId("example_turn_copy");
        $turn->setCreatedAt(Carbon::now());
        $turn->setUpdatedAt(Carbon::now());
        $turn->setScene($scenes[0]);
        $turns[] = $turn;

        $requestIntent = new Intent();
        $requestIntent->setIsRequestIntent(true);
        $requestIntent->setName("Example Request Intent copy");
        $requestIntent->setUid('0x0005');
        $requestIntent->setOdId("intent.app.exampleRequestIntentCopy");
        $requestIntent->setCreatedAt(Carbon::now());
        $requestIntent->setUpdatedAt(Carbon::now());
        $requestIntent->setTurn($turns[0]);
        $requestIntents[] = $requestIntent;

        $responseIntent = new Intent();
        $responseIntent->setIsRequestIntent(true);
        $responseIntent->setName("Example Response Intent copy");
        $responseIntent->setUid('0x0006');
        $responseIntent->setOdId("intent.app.exampleResponseIntentCopy");
        $responseIntent->setCreatedAt(Carbon::now());
        $responseIntent->setUpdatedAt(Carbon::now());
        $responseIntent->setTurn($turns[0]);
        $responseIntents[] = $responseIntent;

        $turns[0]->setRequestIntents(new IntentCollection($requestIntents));
        $turns[0]->setResponseIntents(new IntentCollection($responseIntents));
        $scenes[0]->setTurns(new TurnCollection($turns));
        $conversations[0]->setScenes(new SceneCollection($scenes));
        $scenario->setConversations(new ConversationCollection($conversations));

        return $scenario;
    }

    /**
     * @param callable $create
     */
    protected function mockAndAssertScenarioCreation(callable $create): void
    {
        $scenarioOdId = 'example_scenario';

        $defaultWebchatScenarioData = File::get(resource_path('platform-defaults/webchat.json'));

        /** @var Scenario $fakeScenarioCreated */
        $fakeScenarioCreated = ImportExportSerializer::deserialize($defaultWebchatScenarioData, Scenario::class, 'json');
        $fakeScenarioCreated = $this->addFakeUids($fakeScenarioCreated);
        $fakeScenarioCreated->setOdId($scenarioOdId);
        $fakeScenarioCreated->setName('Example scenario');
        $fakeScenarioCreated->setDescription('An example scenario');
        $fakeScenarioCreated->setCreatedAt(Carbon::now());
        $fakeScenarioCreated->setUpdatedAt(Carbon::now());

        $scenarioUid = $fakeScenarioCreated->getUid();
        $conversationUid = $fakeScenarioCreated->getConversations()->first()->getUid();

        $condition = new Condition(
            'eq',
            ['attribute' => 'user.selected_scenario'],
            ['value' => $fakeScenarioCreated->getUid()]
        );

        $fakeScenarioUpdated = clone($fakeScenarioCreated);
        $fakeScenarioUpdated->setConditions(new ConditionCollection([$condition]));

        // Called in request validation, and on import
        ConversationDataClient::shouldReceive('getAllScenarios')
            ->times(3)
            ->andReturn(new ScenarioCollection([]));

        ScenarioDataClient::shouldReceive('addFullScenarioGraph')
            ->once()
            ->andReturn($fakeScenarioCreated);

        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->twice()
            ->andReturn($fakeScenarioCreated);

        ConversationDataClient::shouldReceive('updateIntent')
            ->twice();

        ConversationDataClient::shouldReceive('updateScenario')
            ->once()
            ->andReturn($fakeScenarioUpdated);

        $create($scenarioOdId, $scenarioUid, $conversationUid);

        $configurations = ComponentConfiguration::all();
        $this->assertCount(2, $configurations);
        $this->assertContains(OpenDialogInterpreter::getComponentId(), $configurations->pluck('component_id'));
        $this->assertContains(WebchatPlatform::getComponentId(), $configurations->pluck('component_id'));
    }

    protected function mockAndAssertScenarioDuplication(callable $duplicate)
    {
        $scenario = self::getFakeScenarioForDuplication();

        // Called during route binding
        ConversationDataClient::shouldReceive('getScenarioByUid')
            ->once()
            ->andReturn($scenario);

        // Called in request validation, and in importing
        ConversationDataClient::shouldReceive('getAllScenarios')
            ->twice()
            ->andReturn(new ScenarioCollection([]));

        $duplicated = null;
        ScenarioDataClient::shouldReceive('addFullScenarioGraph')
            ->once()
            ->andReturnUsing(function ($scenario) use (&$duplicated) {
                $scenario = $scenario->copy();
                $scenario->setUid('0x9999');
                $scenario->setCreatedAt(Carbon::now());
                $scenario->setUpdatedAt(Carbon::now());
                $duplicated = $scenario;
                return $scenario;
            });

        // Called in controller
        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->times(3)
            ->andReturnUsing(
                fn () => $scenario,
                function ($uid) use (&$duplicated) {
                    return $duplicated;
                },
                function ($uid) use (&$duplicated) {
                    $duplicated->setConditions(new ConditionCollection([new Condition(
                        'eq',
                        ['attribute' => 'user.selected_scenario'],
                        ['value' => $uid]
                    )]));

                    return $duplicated;
                }
            );

        // Called when patching the scenario's condition
        ConversationDataClient::shouldReceive('updateScenario')
            ->once()
            ->andReturnUsing(fn ($scenario) => $scenario);

        /** @var ComponentConfiguration $openDialogInterpreter */
        $openDialogInterpreter = ComponentConfiguration::create([
            'name' => ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
            'scenario_id' => '0x0001',
            'component_id' => OpenDialogInterpreter::getComponentId(),
            'configuration' => [
                OpenDialogInterpreterConfiguration::CALLBACKS => [
                    'hello' => 'world',
                ],
            ],
            'active' => true,
        ]);

        /** @var ComponentConfiguration $otherInterpreter */
        $otherInterpreter = ComponentConfiguration::create([
            'name' => 'other',
            'scenario_id' => '0x0001',
            'component_id' => OpenDialogInterpreter::getComponentId(),
            'configuration' => [
                OpenDialogInterpreterConfiguration::CALLBACKS => [
                    'open' => 'dialog',
                ],
            ],
            'active' => false,
        ]);

        $duplicate($scenario->getUid());

        // The default interpreter and platform should have been duplicated too
        $this->assertCount(4, ComponentConfiguration::all());

        $this->assertNotNull(
            ComponentConfiguration::where([
                'name' => ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
                'scenario_id' => '0x0001'
            ])->first()
        );

        $this->assertNotNull(
            ComponentConfiguration::where([
                'name' => 'other',
                'scenario_id' => '0x0001'
            ])->first()
        );

        /** @var ComponentConfiguration $newConfiguration1 */
        $newConfiguration1 = ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
            'scenario_id' => '0x9999'
        ])->first();
        $this->assertNotNull($newConfiguration1);
        $this->assertEquals($openDialogInterpreter->configuration, $newConfiguration1->configuration);
        $this->assertEquals($openDialogInterpreter->active, $newConfiguration1->active);

        $newConfiguration2 = ComponentConfiguration::where([
            'name' => 'other',
            'scenario_id' => '0x9999'
        ])->first();
        $this->assertNotNull($newConfiguration2);
        $this->assertEquals($otherInterpreter->configuration, $newConfiguration2->configuration);
        $this->assertEquals($otherInterpreter->active, $newConfiguration2->active);
    }

    /**
     * @param callable $createFromTemplate
     */
    protected function mockAndAssertScenarioCreationFromTemplate(callable $createFromTemplate)
    {
        /** @var ComponentConfiguration $configuration */
        $configuration = ComponentConfiguration::create([
            'name' => ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
            'scenario_id' => '0x0001',
            'component_id' => OpenDialogInterpreter::getComponentId(),
            'configuration' => [
                OpenDialogInterpreterConfiguration::CALLBACKS => [
                    'hello' => 'world',
                ],
            ],
            'active' => true,
        ]);

        /** @var Template $template */
        $templateData = ScenarioImportExportHelper::getSerializedData(self::getFakeScenarioForDuplication());

        $configuration->delete();

        $template = Template::create([
            'name' => 'My Template',
            'data' => json_decode($templateData, true)
        ]);

        // Called in request validation, and in importing
        ConversationDataClient::shouldReceive('getAllScenarios')
            ->times(3)
            ->andReturn(new ScenarioCollection([]));

        $persistedScenarioId = '0x9999';
        $duplicated = null;
        ScenarioDataClient::shouldReceive('addFullScenarioGraph')
            ->once()
            ->andReturnUsing(function ($scenario) use (&$duplicated, $persistedScenarioId) {
                $scenario = $scenario->copy();
                $scenario->setUid($persistedScenarioId);
                $scenario->setCreatedAt(Carbon::now());
                $scenario->setUpdatedAt(Carbon::now());
                $duplicated = $scenario;
                return $scenario;
            });

        // Called in controller
        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->twice()
            ->andReturnUsing(
                function ($uid) use (&$duplicated) {
                    return $duplicated;
                },
                function ($uid) use (&$duplicated) {
                    $duplicated->setConditions(new ConditionCollection([new Condition(
                        'eq',
                        ['attribute' => 'user.selected_scenario'],
                        ['value' => $uid]
                    )]));

                    return $duplicated;
                }
            );

        // Called when patching the scenario's condition
        ConversationDataClient::shouldReceive('updateScenario')
            ->once()
            ->andReturnUsing(fn ($scenario) => $scenario);

        $createFromTemplate($template->id, $persistedScenarioId);

        $configurations = ComponentConfiguration::all();
        $this->assertCount(1, $configurations);
        $this->assertContains(OpenDialogInterpreter::getComponentId(), $configurations->pluck('component_id'));
    }
}
