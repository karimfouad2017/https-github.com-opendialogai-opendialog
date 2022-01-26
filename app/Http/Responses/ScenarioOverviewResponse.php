<?php

namespace App\Http\Responses;

use App\Http\Responses\FrameData\BaseNode;
use App\Http\Responses\FrameData\Edge;
use App\Http\Responses\FrameData\ConversationGroupNode;
use App\Http\Responses\FrameData\ConversationNode;
use App\Http\Responses\FrameData\IntentCollectionNode;
use App\Http\Responses\FrameData\IntentNode;
use App\Http\Responses\FrameData\ScenarioNode;
use App\Http\Responses\FrameData\SceneGroupNode;
use App\Http\Responses\FrameData\SceneNode;
use App\Http\Responses\FrameData\TurnGroupNode;
use App\Http\Responses\FrameData\TurnNode;
use Illuminate\Support\Collection;
use OpenDialogAi\Core\Conversation\Behavior;
use OpenDialogAi\Core\Conversation\Conversation;
use OpenDialogAi\Core\Conversation\ConversationCollection;
use OpenDialogAi\Core\Conversation\Facades\TransitionDataClient;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\Conversation\Scene;
use OpenDialogAi\Core\Conversation\SceneCollection;
use OpenDialogAi\Core\Conversation\Turn;
use OpenDialogAi\Core\Conversation\TurnCollection;

class ScenarioOverviewResponse
{
    // Levels
    const CONVERSATION_LEVEL = 1;
    const SCENE_LEVEL        = 2;
    const TURN_LEVEL         = 3;
    const INTENT_LEVEL       = 4;

    public Collection $nodes;

    public Collection $connections;

    private array $frameData = [];

    public function __construct()
    {
        $this->nodes = new Collection();
        $this->connections = new Collection();
    }

    public function addScenarioNode(Scenario $scenario, int $level)
    {
        $node = new ScenarioNode($scenario->getName(), $scenario->getUid());

        $this->nodes->add($node);

        $this->addConversationNodes($scenario->getConversations(), $level);

        $this->addNodeConnections($scenario, $level);
    }

    private function addConversationNodes(ConversationCollection $conversations, int $level)
    {
        $conversations->each(function (Conversation $conversation) use ($level) {
            if ($level > self::CONVERSATION_LEVEL && $conversation->hasScenes()) {
                // Add in the conversation group node
                $conversationGroupNode = new ConversationGroupNode($conversation->getName(), $conversation->getUid());
                $this->nodes->add($conversationGroupNode);

                $conversationNode = ConversationNode::groupedNode(
                    $conversation->getName(),
                    $conversation->getUid(),
                    $conversationGroupNode->id
                );

                $this->addSceneNodes($conversation->getScenes(), $level, $conversationGroupNode->id);
            } else {
                $conversationNode = new ConversationNode($conversation->getName(), $conversation->getUid());
            }

            $this->nodes->add($conversationNode);

            // If this is a starting conversation, add the connection
            if ($conversation->getBehaviors()->contains(new Behavior(Behavior::STARTING_BEHAVIOR))) {
                $this->connections[] = Edge::startingEdge(
                    $conversation->getUid(), $conversation->getScenario()->getUid()
                );
            }
        });
    }

    private function addSceneNodes(SceneCollection $scenes, int $level, string $groupId)
    {
        $scenes->each(function (Scene $scene) use ($level, $groupId) {
            if ($level > self::SCENE_LEVEL && $scene->hasTurns()) {
                // Add in the scene group node
                $sceneGroupNode = new SceneGroupNode($scene->getName(), $scene->getUid(), $groupId);
                $this->nodes->add($sceneGroupNode);

                $sceneNode = SceneNode::groupedNode(
                    $scene->getName(),
                    $scene->getUid(),
                    $sceneGroupNode->id
                );

                $this->addTurnNodes($scene->getTurns(), $level, $sceneGroupNode->id);
            } else {
                $sceneNode = SceneNode::groupedNode($scene->getName(), $scene->getUid(), $groupId);
            }

            $this->nodes->add($sceneNode);

            // If this is a starting conversation, add the connection
            if ($scene->getBehaviors()->contains(new Behavior(Behavior::STARTING_BEHAVIOR))) {
                $this->connections[] = Edge::startingEdge(
                    $scene->getUid(), $scene->getConversation()->getUid()
                );
            }
        });
    }

    private function addTurnNodes(TurnCollection $turns, int $level, string $groupId)
    {
        $turns->each(function (Turn $turn) use ($level, $groupId) {
            if ($level > self::TURN_LEVEL && ($turn->hasRequestIntents() || $turn->hasResponseIntents())) {
                // Add in the turn group node
                $turnGroupNode = new TurnGroupNode($turn->getName(), $turn->getUid(), $groupId);
                $this->nodes->add($turnGroupNode);

                $turnNode = TurnNode::groupedNode(
                    $turn->getName(),
                    $turn->getUid(),
                    $turnGroupNode->id
                );

                $this->addIntentNodes(
                    $turn,
                    $level,
                    $turnGroupNode->id);
            } else {
                $turnNode = TurnNode::groupedNode($turn->getName(), $turn->getUid(), $groupId);
            }

            $this->nodes->add($turnNode);

            // If this is a starting conversation, add the connection
            if ($turn->getBehaviors()->contains(new Behavior(Behavior::STARTING_BEHAVIOR))) {
                $this->connections[] = Edge::startingEdge(
                    $turn->getUid(), $turn->getScene()->getUid()
                );
            }
        });
    }

    private function addIntentNodes(Turn $turn, int $level, string $groupId)
    {
        $requests = IntentCollectionNode::fromTurn($turn, $turn->getRequestIntents(), 'request', $groupId);
        $this->nodes->add($requests);
        $this->connections[] = Edge::startingEdge(
            $requests->id, $turn->getUid()
        );

        $responses = IntentCollectionNode::fromTurn($turn, $turn->getResponseIntents(), 'response', $groupId);
        $this->nodes->add($responses);
        $this->connections[] = Edge::startingEdge(
            $responses->id, $turn->getUid()
        );

        $intents = $turn->getResponseIntents()->concat($turn->getRequestIntents());
        $intents->each(function (Intent $intent) use ($level, $groupId, $requests, $responses) {
            $intentNode = IntentNode::groupedNode($intent->getName(), $intent->getUid(), $groupId);
            $this->nodes->add($intentNode);

            $sourceId = $intent->isRequestIntent() ? $requests->id : $responses->id;
            $this->connections[] = Edge::startingEdge($intent->getUid(), $sourceId);
        });
    }

    public function formatResponse()
    {
        $this->nodes->each(function (BaseNode $node) {
            $this->frameData[] = ['data' => $node->toArray()];
        });

        $this->connections->each(function (Edge $connection) {
            $this->frameData[] = ['data' => $connection->toArray()];
        });

        return $this->frameData;
    }

    /**
     * @param Scenario $scenario
     * @param int $level
     */
    protected function addNodeConnections(Scenario $scenario, int $level): void
    {
        $this->addConversationTransitions($scenario, $level);
        $this->addSceneTransitions($scenario, $level);
        $this->addTurnTransitions($scenario, $level);
    }

    /**
     * @param Scenario $scenario
     * @param int $level
     */
    protected function addConversationTransitions(Scenario $scenario, int $level): void
    {
        $conversationIds = $scenario->getConversations()
            ->map(fn(Conversation $conversation) => $conversation->getUid());

        $intentsWithTransitionsToConversation =
            TransitionDataClient::getIncomingConversationTransitions(...$conversationIds);

        $intentsWithTransitionsToConversation->each(function (Intent $intent) use ($level) {
            $target = $intent->getTransition()->getConversation();

            switch ($level) {
                case self::CONVERSATION_LEVEL:
                    $source = $intent->getConversation()->getUid();
                    break;
                case self::SCENE_LEVEL:
                    $source = $intent->getScene()->getUid();
                    break;
                case self::TURN_LEVEL:
                    $source = $intent->getTurn()->getUid();
                    break;
                default:
                    $source = $intent->getUid();
            }

            $this->connections->add(Edge::transitionEdge($target, $source));
        });
    }

    /**
     * @param Scenario $scenario
     * @param int $level
     */
    protected function addSceneTransitions(Scenario $scenario, int $level)
    {
        $sceneIds = new Collection();
        $scenario->getConversations()->each(function ($conversation) use (&$sceneIds) {
            $sceneIds = $sceneIds->concat($conversation->getScenes()->map(fn(Scene $scene) => $scene->getUid()));
        });

        $intentsWithTransitionsToScenes = TransitionDataClient::getIncomingSceneTransitions(...$sceneIds);
        $intentsWithTransitionsToScenes->each(function (Intent $intent) use ($level) {

            switch ($level) {
                case self::CONVERSATION_LEVEL:
                    $target = $intent->getTransition()->getConversation();
                    $source = $intent->getConversation()->getUid();
                    break;
                case self::SCENE_LEVEL:
                    $target = $intent->getTransition()->getScene() ??
                        $intent->getTransition()->getConversation();

                    $source = $intent->getScene()->getUid();
                    break;
                case self::TURN_LEVEL:
                    $target = $intent->getTransition()->getTurn() ??
                        $intent->getTransition()->getScene() ??
                        $intent->getTransition()->getConversation();

                    $source = $intent->getTurn()->getUid();
                    break;
                default:
                    $target = $intent->getTransition()->getTurn() ??
                        $intent->getTransition()->getScene() ??
                        $intent->getTransition()->getConversation();
                    $source = $intent->getUid();
            }
            $this->connections->add(Edge::transitionEdge($target, $source));
        });
    }

    /**
     * @param Scenario $scenario
     * @param int $level
     */
    protected function addTurnTransitions(Scenario $scenario, int $level)
    {
        $turnIds = new Collection();
        $scenario->getConversations()->each(function (Conversation $conversation) use (&$turnIds) {
            $conversation->getScenes()->each(function (Scene $scene) use (&$turnIds) {
                $turnIds = $turnIds->concat($scene->getTurns()->map(fn(Turn $turn) => $turn->getUid()));
            });
        });

        $intentsWithTransitionsToTurns = TransitionDataClient::getIncomingTurnTransitions(...$turnIds);
        $intentsWithTransitionsToTurns->each(function (Intent $intent) use ($level) {
            switch ($level) {
                case self::CONVERSATION_LEVEL:
                    $target = $intent->getTransition()->getConversation();
                    $source = $intent->getConversation()->getUid();
                    break;
                case self::SCENE_LEVEL:
                    $target = $intent->getTransition()->getScene();
                    $source = $intent->getScene()->getUid();
                    break;
                case self::TURN_LEVEL:
                    $target = $intent->getTransition()->getTurn();
                    $source = $intent->getTurn()->getUid();
                    break;
                default:
                    $target = $intent->getTransition()->getTurn() ??
                        $intent->getTransition()->getScene() ??
                        $intent->getTransition()->getConversation();
                    $source = $intent->getUid();
            }
            $this->connections->add(Edge::transitionEdge($target, $source));
        });
    }
}