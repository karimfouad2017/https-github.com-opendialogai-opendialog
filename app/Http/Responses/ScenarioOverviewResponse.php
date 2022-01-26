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
use OpenDialogAi\Core\Conversation\Transition;
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

    private int $level;

    private Collection $include;
    private Collection $exclude;

    public function __construct(Scenario $scenario, int $level, Collection $include, Collection $exclude)
    {
        $this->nodes = new Collection();
        $this->connections = new Collection();
        $this->include = $include;
        $this->exclude = $exclude;

        $this->level = $level;

        $this->nodes->add(new ScenarioNode($scenario->getName(), $scenario->getUid()));

        $this->addConversationNodes($scenario->getConversations());

        $this->addEdges($scenario);
    }

    private function addConversationNodes(ConversationCollection $conversations)
    {
        $conversations->each(function (Conversation $conversation) {

            $conversationNode = new ConversationNode($conversation->getName(), $conversation->getUid());
            if ($this->isConversationOpen($conversation)) {
                $conversationGroupNode = new ConversationGroupNode($conversation->getName(), $conversation->getUid());
                $this->nodes->add($conversationGroupNode);
                $conversationNode->groupId = $conversationGroupNode->id;

                $this->addSceneNodes($conversation->getScenes(), $conversationGroupNode->id);
            }

            $this->nodes->add($conversationNode);

            if ($conversation->getBehaviors()->contains(new Behavior(Behavior::STARTING_BEHAVIOR))) {
                $this->connections[] = Edge::startingEdge($conversation->getUid(), $conversation->getScenario()->getUid());
            }
        });
    }

    private function addSceneNodes(SceneCollection $scenes, string $groupId)
    {
        $scenes->each(function (Scene $scene) use ($groupId) {
            $sceneNode = SceneNode::groupedNode($scene->getName(), $scene->getUid(), $groupId);
            if ($this->isSceneOpen($scene)) {
                $sceneGroupNode = new SceneGroupNode($scene->getName(), $scene->getUid(), $groupId);
                $this->nodes->add($sceneGroupNode);
                $sceneNode->groupId = $sceneGroupNode->id;

                $this->addTurnNodes($scene->getTurns(), $sceneGroupNode->id);
            }

            $this->nodes->add($sceneNode);

            if ($scene->getBehaviors()->contains(new Behavior(Behavior::STARTING_BEHAVIOR))) {
                $this->connections[] = Edge::startingEdge(
                    $scene->getUid(), $scene->getConversation()->getUid()
                );
            }
        });
    }

    private function addTurnNodes(TurnCollection $turns, string $groupId)
    {
        $turns->each(function (Turn $turn) use ($groupId) {
            $turnNode = TurnNode::groupedNode($turn->getName(), $turn->getUid(), $groupId);
            if ($this->isTurnOpen($turn->getUid())) {
                $turnGroupNode = new TurnGroupNode($turn->getName(), $turn->getUid(), $groupId);
                $this->nodes->add($turnGroupNode);

                $turnNode->groupId = $turnGroupNode->id;

                $this->addIntentNodes($turn, $turnGroupNode->id);
            }

            $this->nodes->add($turnNode);

            if ($turn->getBehaviors()->contains(new Behavior(Behavior::STARTING_BEHAVIOR))) {
                $this->connections[] = Edge::startingEdge(
                    $turn->getUid(), $turn->getScene()->getUid()
                );
            }
        });
    }

    private function addIntentNodes(Turn $turn, string $groupId)
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
        $intents->each(function (Intent $intent) use ($groupId, $requests, $responses) {
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
     */
    protected function addEdges(Scenario $scenario): void
    {
        $this->addConversationTransitions($scenario);
        $this->addSceneTransitions($scenario);
        $this->addTurnTransitions($scenario);
    }

    /**
     * Add all transitions that go to a conversation. Work out the source based on what level is open
     *
     * @param Scenario $scenario
     */
    protected function addConversationTransitions(Scenario $scenario): void
    {
        $conversationIds = $scenario->getConversations()
            ->map(fn(Conversation $conversation) => $conversation->getUid());

        $intentsWithTransitionsToConversation =
            TransitionDataClient::getIncomingConversationTransitions(...$conversationIds);

        $intentsWithTransitionsToConversation->each(function (Intent $intent) {
            $target = $intent->getTransition()->getConversation();
            $source = $this->getTransitionSource($intent);

            $this->connections->add(Edge::transitionEdge($target, $source));
        });
    }

    /**
     * @param Scenario $scenario
     */
    protected function addSceneTransitions(Scenario $scenario)
    {
        $sceneIds = new Collection();
        $scenario->getConversations()->each(function ($conversation) use (&$sceneIds) {
            $sceneIds = $sceneIds->concat($conversation->getScenes()->map(fn(Scene $scene) => $scene->getUid()));
        });

        $intentsWithTransitionsToScenes = TransitionDataClient::getIncomingSceneTransitions(...$sceneIds);
        $intentsWithTransitionsToScenes->each(function (Intent $intent) {
            $source = $this->getTransitionSource($intent);
            $target = $this->getTransitionTarget($intent->getTransition());
            $this->connections->add(Edge::transitionEdge($target, $source));
        });
    }

    /**
     * @param Scenario $scenario
     */
    protected function addTurnTransitions(Scenario $scenario)
    {
        $turnIds = new Collection();
        $scenario->getConversations()->each(function (Conversation $conversation) use (&$turnIds) {
            $conversation->getScenes()->each(function (Scene $scene) use (&$turnIds) {
                $turnIds = $turnIds->concat($scene->getTurns()->map(fn(Turn $turn) => $turn->getUid()));
            });
        });

        $intentsWithTransitionsToTurns = TransitionDataClient::getIncomingTurnTransitions(...$turnIds);
        $intentsWithTransitionsToTurns->each(function (Intent $intent) {
            $source = $this->getTransitionSource($intent);
            $target = $this->getTransitionTarget($intent->getTransition());
            $this->connections->add(Edge::transitionEdge($target, $source));
        });
    }

    /**
     * @param Conversation $conversation
     * @return bool
     */
    private function isConversationOpen(Conversation $conversation): bool
    {
        return (($this->level > self::CONVERSATION_LEVEL) || $this->include->contains($conversation->getUid()))
            && $conversation->hasScenes()
            && !$this->exclude->contains($conversation->getUid());
    }

    /**
     * @param Scene $sceneId
     * @return bool
     */
    private function isSceneOpen(Scene $sceneId): bool
    {
        return (($this->level > self::SCENE_LEVEL) || $this->include->contains($sceneId->getUid()))
            && $sceneId->hasTurns()
            && !$this->exclude->contains($sceneId->getUid());
    }

    /**
     * @param string $turnId
     * @return bool
     */
    private function isTurnOpen(string $turnId): bool
    {
        return (($this->level > self::TURN_LEVEL) || $this->include->contains($turnId))
            && !$this->exclude->contains($turnId);
    }

    /**
     * @param Intent $intent
     * @return string
     */
    protected function getTransitionSource(Intent $intent): string
    {
        if ($this->isTurnOpen($intent->getTurn()->getUid())) {
            $source = $intent->getUid();
        } else if ($this->isSceneOpen($intent->getScene())) {
            $source = $intent->getTurn()->getUid();
        } else if ($this->isConversationOpen($intent->getConversation())) {
            $source = $intent->getScene()->getUid();
        } else {
            $source = $intent->getConversation()->getUid();
        }
        return $source;
    }
    
    private function getTransitionTarget(Transition $transition): string
    {
        if ($transition->getTurn()) {
            if ($this->nodes->filter(fn (BaseNode $node) => $node->id === $transition->getTurn())->count()) {
                return $transition->getTurn();
            } else if ($this->nodes->filter(fn (BaseNode $node) => $node->id === $transition->getScene())->count()) {
                return $transition->getScene();
            } else {
                return $transition->getConversation();
            }
        } else if ($transition->getScene()) {
            if ($this->nodes->filter(fn (BaseNode $node) => $node->id === $transition->getScene())->count()) {
                return $transition->getScene();
            } else {
                return $transition->getConversation();
            }
        } else {
            return $transition->getConversation();
        }
    }
}
