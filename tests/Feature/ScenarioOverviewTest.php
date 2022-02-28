<?php

namespace Tests\Feature;

use App\User;
use OpenDialogAi\Core\Conversation\Behavior;
use OpenDialogAi\Core\Conversation\BehaviorsCollection;
use OpenDialogAi\Core\Conversation\Conversation;
use OpenDialogAi\Core\Conversation\ConversationCollection;
use OpenDialogAi\Core\Conversation\Facades\ScenarioDataClient;
use OpenDialogAi\Core\Conversation\Facades\TransitionDataClient;
use OpenDialogAi\Core\Conversation\IntentCollection;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\Conversation\Scene;
use OpenDialogAi\Core\Conversation\SceneCollection;
use OpenDialogAi\Core\Conversation\Turn;
use OpenDialogAi\Core\Conversation\TurnCollection;
use Tests\TestCase;

class ScenarioOverviewTest extends TestCase
{
    protected User $user;

    public function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create();
    }

    public function testAuth()
    {
        $this->json('GET', '/admin/api/scenario-overview')
            ->assertStatus(401);
    }

    public function testValidation()
    {
        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/scenario-overview')
            ->assertStatus(422);

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/scenario-overview?level=1&include=1,2&exclude=23,4')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('scenario');

        $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/scenario-overview?scenario=scenario&level=word')
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('level');
    }

    public function testSimpleScenarioResponse()
    {
        $scenarioId = "0x0";
        $simpleScenario = $this->getSimpleScenario($scenarioId);
        $simpleScenario->setConversations(new ConversationCollection());

        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->once()
            ->with($scenarioId)
            ->andReturn($simpleScenario);

        $this->noTransitons();

        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=1")
            ->assertStatus(200)
            ->assertJsonFragment(['type' => 'scenario', 'label' => 'Test Scenario'])
            ->assertJsonCount(1);
    }

    public function testSingleConversationNotStarting()
    {
        $scenarioId = "0x0";

        $simpleScenario = $this->getSimpleScenario($scenarioId);
        $conversation = $this->getConversation($simpleScenario);
        $simpleScenario->setConversations(new ConversationCollection([$conversation]));

        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->once()
            ->with($scenarioId)
            ->andReturn($simpleScenario);

        $this->noTransitons();

        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=1")
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJsonFragment(
                ['type' => 'conversation', 'label' => $conversation->getName(), 'id' => $conversation->getUid()]
            );
    }

    public function testSingleConversationStarting()
    {
        $scenarioId = "0x0";

        $simpleScenario = $this->getSimpleScenario($scenarioId);
        $conversation = $this->getConversation($simpleScenario);

        $conversation->setBehaviors(new BehaviorsCollection([new Behavior('STARTING')]));

        $simpleScenario->setConversations(new ConversationCollection([$conversation]));

        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->once()
            ->with($scenarioId)
            ->andReturn($simpleScenario);

        $this->noTransitons();

        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=1")
            ->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJsonFragment(
                ['status' => 'starting', 'source' => $scenarioId, 'target' => $conversation->getUid()]
            );
    }

    public function testLevels()
    {
        $scenarioId = "0x0";

        $simpleScenario = $this->getSimpleScenario($scenarioId);
        $conversation = $this->getConversation($simpleScenario);

        $scene = new Scene($conversation);
        $scene->setName('Scene');
        $scene->setOdId('scene');
        $scene->setUid('0x2');
        $scene->setTurns(new TurnCollection());

        $conversation->addScene($scene);

        $turn = new Turn($scene);
        $turn->setName('Turn');
        $turn->setOdId('turn');
        $turn->setUid('0x3');
        $turn->setRequestIntents(new IntentCollection());
        $turn->setResponseIntents(new IntentCollection());

        $scene->addTurn($turn);

        $simpleScenario->setConversations(new ConversationCollection([$conversation]));

        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->with($scenarioId)
            ->andReturn($simpleScenario);

        $this->noTransitons();

        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=1")
            ->assertStatus(200)
            ->assertJsonCount(2) // scenes not shown at level 1
            ->assertJsonMissing(['type' => 'scene']);

        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=2")
            ->assertStatus(200)
            ->assertJsonCount(4) // should include the conversation group
            ->assertJsonFragment(['type' => 'conversation_group'])
            ->assertJsonFragment(['type' => 'scene'])
            ->assertJsonMissing(['type' => 'turn']);

        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=3")
            ->assertStatus(200)
            ->assertJsonCount(6) // should include the scene group
            ->assertJsonFragment(['type' => 'conversation_group'])
            ->assertJsonFragment(['type' => 'scene'])
            ->assertJsonFragment(['type' => 'scene_group'])
            ->assertJsonFragment(['type' => 'turn']);
    }

    public function testinclude()
    {
        $scenarioId = "0x0";

        $simpleScenario = $this->getSimpleScenario($scenarioId);
        $conversation = $this->getConversation($simpleScenario);

        $scene = new Scene($conversation);
        $scene->setName('Scene');
        $scene->setOdId('scene');
        $scene->setUid('0x2');
        $scene->setTurns(new TurnCollection());

        $conversation->addScene($scene);

        $turn = new Turn($scene);
        $turn->setName('Turn');
        $turn->setOdId('turn');
        $turn->setUid('0x3');
        $turn->setRequestIntents(new IntentCollection());
        $turn->setResponseIntents(new IntentCollection());

        $scene->addTurn($turn);

        $simpleScenario->setConversations(new ConversationCollection([$conversation]));

        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->with($scenarioId)
            ->andReturn($simpleScenario);

        $this->noTransitons();

        // Include the conversation id
        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=1&include=0x1")
            ->assertStatus(200)
            ->assertJsonCount(4)
            ->assertJsonFragment(['type' => 'conversation_group'])
            ->assertJsonFragment(['type' => 'scene']);

        // Include the conversation and scene id
        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=1&include=0x1,0x2")
            ->assertStatus(200)
            ->assertJsonCount(6) // should include the scene group
            ->assertJsonFragment(['type' => 'conversation_group'])
            ->assertJsonFragment(['type' => 'scene'])
            ->assertJsonFragment(['type' => 'scene_group'])
            ->assertJsonFragment(['type' => 'turn']);
    }

    public function testExclude()
    {
        $scenarioId = "0x0";

        $simpleScenario = $this->getSimpleScenario($scenarioId);
        $conversation = $this->getConversation($simpleScenario);

        $scene = new Scene($conversation);
        $scene->setName('Scene');
        $scene->setOdId('scene');
        $scene->setUid('0x2');
        $scene->setTurns(new TurnCollection());

        $conversation->addScene($scene);

        $turn = new Turn($scene);
        $turn->setName('Turn');
        $turn->setOdId('turn');
        $turn->setUid('0x3');
        $turn->setRequestIntents(new IntentCollection());
        $turn->setResponseIntents(new IntentCollection());

        $scene->addTurn($turn);

        $simpleScenario->setConversations(new ConversationCollection([$conversation]));

        ScenarioDataClient::shouldReceive('getFullScenarioGraph')
            ->with($scenarioId)
            ->andReturn($simpleScenario);

        $this->noTransitons();

        // Exclude conversation
        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=3&exclude=0x1")
            ->assertStatus(200)
            ->assertJsonCount(2);

        // exclude scene
        $this->actingAs($this->user, 'api')
            ->json('GET', "/admin/api/scenario-overview?scenario=$scenarioId&level=3&exclude=0x2")
            ->assertStatus(200)
            ->assertJsonCount(4);
    }

    /**
     * @param string $scenarioId
     * @return Scenario
     */
    private function getSimpleScenario(string $scenarioId): Scenario
    {
        $simpleScenario = Scenario::createPartial();
        $simpleScenario->setUid($scenarioId);
        $simpleScenario->setName('Test Scenario');
        return $simpleScenario;
    }

    /**
     * @param Scenario $simpleScenario
     * @return Conversation
     */
    private function getConversation(Scenario $simpleScenario): Conversation
    {
        $conversation = new Conversation($simpleScenario);
        $conversation->setUid("0x1");
        $conversation->setName("test conversation");
        $conversation->setScenes(new SceneCollection());
        return $conversation;
    }

    private function noTransitons(): void
    {
        TransitionDataClient::shouldReceive('getIncomingConversationTransitions')
            ->andReturn(new IntentCollection());

        TransitionDataClient::shouldReceive('getIncomingSceneTransitions')
            ->andReturn(new IntentCollection());

        TransitionDataClient::shouldReceive('getIncomingTurnTransitions')
            ->andReturn(new IntentCollection());
    }
}

