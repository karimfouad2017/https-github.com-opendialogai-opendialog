<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Facades\Serializer;
use App\Http\Requests\ConversationObjectDuplicationRequest;
use App\Http\Requests\ConversationRequest;
use App\Http\Requests\ScenarioRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\ScenarioDeploymentKeyResource;
use App\Http\Resources\ScenarioResource;
use App\ImportExportHelpers\PathSubstitutionHelper;
use App\ImportExportHelpers\ScenarioImportExportHelper;
use App\ScenarioAccessToken;
use App\Template;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Components\Configuration\ConfigurationDataHelper;
use OpenDialogAi\Core\Conversation\Behavior;
use OpenDialogAi\Core\Conversation\BehaviorsCollection;
use OpenDialogAi\Core\Conversation\Condition;
use OpenDialogAi\Core\Conversation\ConditionCollection;
use OpenDialogAi\Core\Conversation\Conversation;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Facades\ScenarioDataClient;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Conversation\IntentCollection;
use OpenDialogAi\Core\Conversation\MessageTemplate;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\Conversation\Scene;
use OpenDialogAi\Core\Conversation\Transition;
use OpenDialogAi\Core\Conversation\Turn;
use OpenDialogAi\Core\InterpreterEngine\OpenDialog\OpenDialogInterpreterConfiguration;
use OpenDialogAi\InterpreterEngine\Interpreters\OpenDialogInterpreter;
use OpenDialogAi\MessageBuilder\MessageMarkUpGenerator;
use OpenDialogAi\PlatformEngine\Components\WebchatPlatform;
use OpenDialogAi\Webchat\WebchatSetting;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScenariosController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Returns a collection of scenarios.
     *
     * @return ScenarioResource
     */
    public function index(): ScenarioResource
    {
        $scenarios = ConversationDataClient::getAllScenarios();
        return new ScenarioResource($scenarios);
    }

    /**
     * Display the specified scenario.
     *
     * @param Scenario $scenario
     * @return ScenarioResource
     */
    public function show(Scenario $scenario): ScenarioResource
    {
        return new ScenarioResource($scenario);
    }

    /**
     * Display the specified scenario deployment key.
     *
     * @param Scenario $scenario
     * @return ScenarioDeploymentKeyResource
     */
    public function showDeploymentKey(Scenario $scenario): ScenarioDeploymentKeyResource
    {
        $deploymentKey = ScenarioAccessToken::where('scenario_id', $scenario->getUid())->first();
        if (!$deploymentKey) {
            abort(404);
        }
        return new ScenarioDeploymentKeyResource($deploymentKey);
    }

    /**
     * Returns a collection of conversations for a particular scenario.
     *
     * @param Scenario $scenario
     * @return ConversationResource
     */
    public function showConversationsByScenario(Scenario $scenario): ConversationResource
    {
        $conversations = ConversationDataClient::getAllConversationsByScenario($scenario);
        return new ConversationResource($conversations);
    }

    /**
     * Store a newly created conversation against a particular scenario.
     *
     * @param Scenario $scenario
     * @param ConversationRequest $request
     * @return ConversationResource
     */
    public function storeConversationsAgainstScenario(Scenario $scenario, ConversationRequest $request): ConversationResource
    {
        $newConversation = Serializer::deserialize($request->getContent(), Conversation::class, 'json');
        $newConversation->setScenario($scenario);
        $conversation = ConversationDataClient::addConversation($newConversation);

        return new ConversationResource($conversation);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ScenarioRequest $request
     * @return JsonResponse
     */
    public function store(ScenarioRequest $request): JsonResponse
    {
        /** @var Scenario $newScenario */
        $newScenario = Serializer::deserialize($request->getContent(), Scenario::class, 'json');

        if ($newScenario->getInterpreter() === "") {
            $newScenario->setInterpreter(ConfigurationDataHelper::OPENDIALOG_INTERPRETER);
        }

        $persistedScenario = $this->createDefaultConversations($newScenario);

        $this->createDefaultConfigurationsForScenario($persistedScenario->getUid());

        // Add a new condition to the scenario now that it has an ID
        $persistedScenario = $this->setDefaultScenarioCondition($persistedScenario);
        $updatedScenario = ConversationDataClient::updateScenario($persistedScenario);

        return (new ScenarioResource($updatedScenario))->response()->setStatusCode(201);
    }

    /**
     * @param Scenario $scenario
     * @return Scenario
     */
    private function createDefaultConversations(Scenario $scenario): Scenario
    {
        $scenarioName = $scenario->getName();
        $scenarioNameAsId = preg_replace('/\s/', '', ucwords($scenario->getOdId()));
        $welcomeOutgoingIntentId = "intent.app.welcomeResponseFor$scenarioNameAsId";
        $noMatchOutgoingIntentId = "intent.app.noMatchResponse$scenarioNameAsId";

        $incomingIntentId = "intent.app.autogenerated";
        $welcomeBotMessage = (new MessageMarkUpGenerator(false))
            ->addButtonMessage(
                "Hi! This is the default welcome message for the $scenarioName Scenario.",
                [['text' => 'OK', 'callback' => $incomingIntentId, 'value' => null]],
                false
            );
        $welcomeName = 'Welcome';
        $welcomeConversation = $this->createAtomicCallbackConversation(
            $scenario,
            $welcomeName,
            $incomingIntentId,
            "This is the user's first response to what the app asked for in the welcome conversation",
            $welcomeOutgoingIntentId,
            "Hi! This is the default welcome message for the $scenarioName Scenario.",
            true,
            $welcomeBotMessage->getMarkUp(),
            false
        );

        $triggerName = 'Trigger';

        $requestWelcomeIntent = new Intent(null, Intent::USER);
        $requestWelcomeIntent->setName('intent.core.welcome');
        $requestWelcomeIntent->setOdId('intent.core.welcome');
        $requestWelcomeIntent->setSampleUtterance('- User triggers conversational application -');

        $requestRestartIntent = new Intent(null, Intent::USER);
        $requestRestartIntent->setName('intent.core.restart');
        $requestRestartIntent->setOdId('intent.core.restart');
        $requestRestartIntent->setSampleUtterance('- User restarts conversational application -');

        $requestIntents = new IntentCollection([$requestWelcomeIntent, $requestRestartIntent]);

        $triggerTurn = $this->createRequestOnlyCallbackConversation(
            $scenario,
            $triggerName,
            $requestIntents
        );

        $noMatchConversation = $this->createAtomicCallbackConversation(
            $scenario,
            'No Match',
            'intent.core.NoMatch',
            '[no match]',
            $noMatchOutgoingIntentId,
            'Sorry, I didn\'t understand that'
        );

        $scenario->addConversation($welcomeConversation);
        $scenario->addConversation($triggerTurn->getConversation());
        $scenario->addConversation($noMatchConversation);

        $scenario = ScenarioDataClient::addFullScenarioGraph($scenario);
        $scenario = ScenarioDataClient::getFullScenarioGraph($scenario->getUid());

        $welcomeId = $this->convertNameToId($welcomeName);
        $triggerId = $this->convertNameToId($triggerName);

        $welcomeIntent = $this->getRequestIntentsForRequestOnlyConversation($scenario, $welcomeId)->first();

        $triggerWelcomeIntent = $this->getRequestIntentsForRequestOnlyConversation($scenario, $triggerId)->first();
        $triggerRestartIntent = $this->getRequestIntentsForRequestOnlyConversation($scenario, $triggerId)->last();

        $triggerWelcomeIntent->setTransition(new Transition(
            $welcomeIntent->getConversation()->getUid(),
            $welcomeIntent->getScene()->getUid(),
            $welcomeIntent->getTurn()->getUid()
        ));
        $triggerRestartIntent->setTransition(new Transition(
            $welcomeIntent->getConversation()->getUid(),
            $welcomeIntent->getScene()->getUid(),
            $welcomeIntent->getTurn()->getUid()
        ));

        ConversationDataClient::updateIntent($triggerWelcomeIntent);
        ConversationDataClient::updateIntent($triggerRestartIntent);

        return $scenario;
    }

    public static function createDefaultConfigurationsForScenario(string $scenarioId)
    {
        ComponentConfiguration::create([
            'name' => ConfigurationDataHelper::OPENDIALOG_INTERPRETER,
            'scenario_id' => $scenarioId,
            'component_id' => OpenDialogInterpreter::getComponentId(),
            'configuration' => [
                OpenDialogInterpreterConfiguration::CALLBACKS => [
                    'WELCOME' => 'intent.core.welcome',
                ],
                OpenDialogInterpreterConfiguration::ENABLE_SIMILARITY_EVALUATION => true,
            ],
            'active' => true,
        ]);

        ComponentConfiguration::create([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
            'scenario_id' => $scenarioId,
            'component_id' => WebchatPlatform::getComponentId(),
            'configuration' => self::getDefaultWebchatSettings(),
            'active' => true,
        ]);
    }

    /**
     * Update the specified scenario.
     *
     * @param ScenarioRequest $request
     * @param Scenario $scenario
     * @return ScenarioResource
     */
    public function update(ScenarioRequest $request, Scenario $scenario): ScenarioResource
    {
        $scenarioUpdate = Serializer::deserialize($request->getContent(), Scenario::class, 'json');
        $updatedScenario = ConversationDataClient::updateScenario($scenarioUpdate);
        return new ScenarioResource($updatedScenario);
    }

    /**
     * Destroy the specified scenario.
     *
     * @param Scenario $scenario
     * @return Response $response
     */
    public function destroy(Scenario $scenario): Response
    {
        if (ConversationDataClient::deleteScenarioByUid($scenario->getUid())) {
            ComponentConfiguration::where([
                'scenario_id' => $scenario->getUid()
            ])->delete();

            return response()->noContent(200);
        } else {
            return response('Error deleting scenario, check the logs', 500);
        }
    }

    /**
     * @param ConversationObjectDuplicationRequest $request
     * @param Scenario|null $scenario
     * @param Template|null $template
     * @return ScenarioResource
     */
    public function duplicate(
        ConversationObjectDuplicationRequest $request,
        Scenario $scenario = null,
        Template $template = null
    ): ScenarioResource {
        if (!is_null($template)) {
            // Creating from template

            $data = $template->data;
            $originalTemplateOdId = $data['od_id'];

            $tempScenario = new Scenario();
            $tempScenario->setOdId($originalTemplateOdId);
            $tempScenario->setName($data['name']);
            $tempScenario->setDescription($data['description']);

            $tempScenario = $request->setUniqueOdId($tempScenario, null, false, true);
            $tempScenario = $request->setDescription($tempScenario);

            $data['od_id'] = $tempScenario->getOdId();
            $data['name'] = $tempScenario->getName();
            $data['description'] = $tempScenario->getDescription();

            $oldPath = PathSubstitutionHelper::createPath($originalTemplateOdId);
            $newPath = PathSubstitutionHelper::createPath($tempScenario->getOdId());

            $data = json_decode(str_replace($oldPath, $newPath, json_encode($data)), true);
        } elseif (!is_null($scenario)) {
            // Duplicating from scenario

            $scenario = ScenarioDataClient::getFullScenarioGraph($scenario->getUid());
            $scenario = $request->setUniqueOdId($scenario);
            $data = json_decode(ScenarioImportExportHelper::getSerializedData($scenario), true);
        }

        $scenario = ScenarioImportExportHelper::importScenarioFromString(json_encode($data));

        $scenario->setCreatedAt(Carbon::now());
        $scenario->setUpdatedAt(Carbon::now());

        return new ScenarioResource($scenario);
    }

    public function export(Scenario $scenario): StreamedResponse
    {
        $scenario = ScenarioDataClient::getFullScenarioGraph($scenario->getUid());
        $data = json_decode(ScenarioImportExportHelper::getSerializedData($scenario), true);

        $odId = $scenario->getOdId();
        $fileName = ScenarioImportExportHelper::suffixScenarioFileName($odId);

        return response()->streamDownload(
            fn () => print(json_encode($data)),
            $fileName,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param Scenario $scenario
     * @param string $name
     * @param string $incomingIntentId
     * @param string $incomingSampleUtterance
     * @param string $outgoingIntentId
     * @param string $outgoingSampleUtterance
     * @param bool $botLed = false
     * @param string|null $botMessage
     * @param bool $isStarting
     * @return Conversation
     */
    private function createAtomicCallbackConversation(
        Scenario $scenario,
        string $name,
        string $incomingIntentId,
        string $incomingSampleUtterance,
        string $outgoingIntentId,
        string $outgoingSampleUtterance,
        bool $botLed = false,
        string $botMessage = null,
        bool $isStarting = true
    ): Conversation {
        $nameAsId = $this->convertNameToId($name);

        $turn = $this->createConversationToTurn($scenario, $name, $nameAsId, $isStarting);

        $incomingIntent = new Intent($turn, Intent::USER);
        $incomingIntent->setIsRequestIntent(!$botLed);
        $incomingIntent->setName($incomingIntentId);
        $incomingIntent->setOdId($incomingIntentId);
        $incomingIntent->setDescription('Automatically generated');
        $incomingIntent->setSampleUtterance($incomingSampleUtterance);
        $incomingIntent->setInterpreter(ConfigurationDataHelper::OPENDIALOG_INTERPRETER);
        $incomingIntent->setConfidence(1);
        $incomingIntent->setCreatedAt(Carbon::now());
        $incomingIntent->setUpdatedAt(Carbon::now());

        $outgoingIntent = new Intent($turn, Intent::APP);
        $outgoingIntent->setIsRequestIntent($botLed);
        $outgoingIntent->setName($outgoingIntentId);
        $outgoingIntent->setOdId($outgoingIntentId);
        $outgoingIntent->setDescription('Automatically generated');
        $outgoingIntent->setSampleUtterance($outgoingSampleUtterance);
        $outgoingIntent->setConfidence(1);

        if (!$botLed) {
            $outgoingIntent->setBehaviors(new BehaviorsCollection([new Behavior(Behavior::COMPLETING_BEHAVIOR)]));
        }

        $outgoingIntent->setCreatedAt(Carbon::now());
        $outgoingIntent->setUpdatedAt(Carbon::now());

        if ($botLed) {
            $turn->addRequestIntent($outgoingIntent);
            $turn->addResponseIntent($incomingIntent);
        } else {
            $turn->addRequestIntent($incomingIntent);
            $turn->addResponseIntent($outgoingIntent);
        }

        $messageTemplate = new MessageTemplate();
        $messageTemplate->setName('auto generated');
        $messageTemplate->setOdId('auto_generated');

        if ($botMessage) {
            $messageTemplate->setMessageMarkup($botMessage);
        } else {
            $messageTemplate->setMessageMarkup(
                (new MessageMarkUpGenerator())->addTextMessage($outgoingSampleUtterance)->getMarkUp()
            );
        }

        $outgoingIntent->addMessageTemplate($messageTemplate);

        return $turn->getConversation();
    }

    /**
     * @param Scenario $scenario
     * @param string $name
     * @param IntentCollection $intents
     * @return Turn
     */
    private function createRequestOnlyCallbackConversation(
        Scenario $scenario,
        string $name,
        IntentCollection $intents
    ): Turn {
        $nameAsId = $this->convertNameToId($name);

        $turn = $this->createConversationToTurn($scenario, $name, $nameAsId);

        foreach ($intents as $intent) {
            $this->createRequestIntent($turn, $intent);
        }

        return $turn;
    }

    /**
     * @param Scenario $scenario
     * @param string $name
     * @param string $nameAsId
     * @param bool $isStarting
     * @return Turn
     */
    private function createConversationToTurn(Scenario $scenario, string $name, string $nameAsId, bool $isStarting = true): Turn
    {
        $conversation = new Conversation($scenario);
        $conversation->setName("$name Conversation");
        $conversation->setOdId(sprintf('%s_conversation', $nameAsId));
        $conversation->setDescription('Automatically generated');
        $conversation->setInterpreter('');

        if ($isStarting) {
            $conversation->setBehaviors(new BehaviorsCollection([new Behavior(Behavior::STARTING_BEHAVIOR)]));
        }

        $conversation->setCreatedAt(Carbon::now());
        $conversation->setUpdatedAt(Carbon::now());

        $scene = new Scene($conversation);
        $scene->setName("$name Scene");
        $scene->setOdId(sprintf('%s_scene', $nameAsId));
        $scene->setDescription('Automatically generated');
        $scene->setInterpreter('');
        $scene->setBehaviors(new BehaviorsCollection([new Behavior(Behavior::STARTING_BEHAVIOR)]));
        $scene->setCreatedAt(Carbon::now());
        $scene->setUpdatedAt(Carbon::now());

        $turn = new Turn($scene);
        $turn->setName("$name Turn");
        $turn->setOdId(sprintf('%s_turn', $nameAsId));
        $turn->setDescription('Automatically generated');
        $turn->setInterpreter('');
        $turn->setBehaviors(new BehaviorsCollection([
            new Behavior(Behavior::STARTING_BEHAVIOR),
            new Behavior(Behavior::OPEN_BEHAVIOR),
        ]));
        $turn->setCreatedAt(Carbon::now());
        $turn->setUpdatedAt(Carbon::now());

        $scene->addTurn($turn);
        $conversation->addScene($scene);

        return $turn;
    }

    /**
     * @param string $name
     * @return string
     */
    private function convertNameToId(string $name)
    {
        return preg_replace('/\s/', '_', strtolower($name));
    }

    /**
     * @param Scenario $scenario
     * @param string $id
     * @return IntentCollection
     */
    private function getRequestIntentsForRequestOnlyConversation(Scenario $scenario, string $id): IntentCollection
    {
        /** @var Conversation $conversation */
        $conversation = $scenario->getConversations()->getObjectsWithId(sprintf('%s_conversation', $id))->first();

        /** @var Scene $scene */
        $scene = $conversation->getScenes()->first();

        /** @var Turn $turn */
        $turn = $scene->getTurns()->first();

        return $turn->getRequestIntents();
    }

    /**
     * @param Scenario $scenario
     * @return Scenario
     */
    private function setDefaultScenarioCondition(Scenario $scenario): Scenario
    {
        $condition = new Condition(
            'eq',
            ['attribute' => 'user.selected_scenario'],
            ['value' => $scenario->getUid()]
        );

        $scenario->setConditions(new ConditionCollection([$condition]));

        return $scenario;
    }

    /**
     * @param Turn $turn
     * @param Intent $requestIntent
     */
    private function createRequestIntent(Turn $turn, Intent $requestIntent): void
    {
        $requestIntent->setIsRequestIntent(true);
        $requestIntent->setDescription('Automatically generated');
        $requestIntent->setConfidence(1);

        $speaker = $requestIntent->getSpeaker();

        if ($speaker === Intent::USER) {
            $requestIntent->setInterpreter(ConfigurationDataHelper::OPENDIALOG_INTERPRETER);
        } else {
            $requestIntent->setInterpreter('');
        }

        $requestIntent->setTurn($turn);
        $turn->addRequestIntent($requestIntent);

        if ($speaker === Intent::APP) {
            $messageTemplate = new MessageTemplate();
            $messageTemplate->setName('auto generated');
            $messageTemplate->setOdId('auto_generated');
            $messageTemplate->setMessageMarkup(
                (new MessageMarkUpGenerator())->addTextMessage($requestIntent->getSampleUtterance())->getMarkUp()
            );

            $requestIntent->addMessageTemplate($messageTemplate);
        }
    }

    /**
     * Duplicates all configurations for original scenario to new scenario
     *
     * @param string $originalUid
     * @param string $newUid
     */
    private function duplicateConfigurationsForScenario(string $originalUid, string $newUid): void
    {
        $configurations = ComponentConfiguration::where('scenario_id', $originalUid)->get();

        $configurations->each(function (ComponentConfiguration $configuration) use ($newUid) {
            $duplicate = $configuration->replicate();
            $duplicate->scenario_id = $newUid;
            $duplicate->save();
        });
    }

    /**
     * This must be separate from the method in the CreateWebchatPlatform class, as _this_ method can be updated, whereas
     * that method (and class) shouldn't be changed (instead a new update class that edits existing settings
     * should be created)
     *
     * @return array
     */
    public static function getDefaultWebchatSettings(): array
    {
        $commentsUrl = 'http://example.com';
        $token = 'ApiTokenValue';

        return [
            WebchatSetting::GENERAL => [
                WebchatSetting::OPEN => true,
                WebchatSetting::TEAM_NAME => "",
                WebchatSetting::LOGO => "/images/homepage-logo.svg",
                WebchatSetting::MESSAGE_DELAY => '500',
                WebchatSetting::COLLECT_USER_IP => true,
                WebchatSetting::CHATBOT_AVATAR_PATH => "/vendor/webchat/images/avatar.svg",
                WebchatSetting::CHATBOT_NAME => 'OpenDialog',
                WebchatSetting::DISABLE_CLOSE_CHAT => false,
                WebchatSetting::USE_HUMAN_AVATAR => false,
                WebchatSetting::USE_HUMAN_NAME => false,
                WebchatSetting::USE_BOT_AVATAR => true,
                WebchatSetting::USE_BOT_NAME => false,
                WebchatSetting::CHATBOT_FULLPAGE_CSS_PATH => "",
                WebchatSetting::CHATBOT_CSS_PATH => "",
                WebchatSetting::PAGE_CSS_PATH => "",
                WebchatSetting::SHOW_TEXT_INPUT_WITH_EXTERNAL_BUTTONS => false,
                WebchatSetting::FORM_RESPONSE_TEXT => null,
                WebchatSetting::SCROLL_TO_FIRST_NEW_MESSAGE => false,
                WebchatSetting::SHOW_HEADER_BUTTONS_ON_FULL_PAGE_MESSAGES => false,
                WebchatSetting::SHOW_HEADER_CLOSE_BUTTON => false,
                WebchatSetting::TYPING_INDICATOR_STYLE => "",
                WebchatSetting::SHOW_RESTART_BUTTON => false,
                WebchatSetting::SHOW_DOWNLOAD_BUTON => true,
                WebchatSetting::SHOW_END_CHAT_BUTON => false,
                WebchatSetting::HIDE_DATETIME_MESSAGE => true,
                WebchatSetting::RESTART_BUTTON_CALLBACK => 'intent.core.restart',
                WebchatSetting::MESSAGE_ANIMATION => false,
                WebchatSetting::HIDE_TYPING_INDICATOR_ON_INTERNAL_MESSAGES => false,
                WebchatSetting::HIDE_MESSAGE_TIME => true,
                WebchatSetting::NEW_USER_START_MINIMIZED => false,
                WebchatSetting::RETURNING_USER_START_MINIMIZED => false,
                WebchatSetting::ONGOING_USER_START_MINIMIZED => false,
                WebchatSetting::NEW_USER_OPEN_CALLBACK => 'WELCOME',
                WebchatSetting::RETURNING_USER_OPEN_CALLBACK => 'WELCOME',
                WebchatSetting::ONGOING_USER_OPEN_CALLBACK => '',
                WebchatSetting::VALID_PATH => ["*"],
            ],
            WebchatSetting::COLOURS => [
                WebchatSetting::HEADER_BACKGROUND => '#1b2956',
                WebchatSetting::HEADER_TEXT => '#ffffff',
                WebchatSetting::LAUNCHER_BACKGROUND => '#1b2956',
                WebchatSetting::MESSAGE_LIST_BACKGROUND => '#1b2956',
                WebchatSetting::SENT_MESSAGE_BACKGROUND => '#7fdad1',
                WebchatSetting::SENT_MESSAGE_TEXT => '#1b2956',
                WebchatSetting::RECEIVED_MESSAGE_BACKGROUND => '#ffffff',
                WebchatSetting::RECEIVED_MESSAGE_TEXT => '#1b2956',
                WebchatSetting::USER_INPUT_BACKGROUND => '#ffffff',
                WebchatSetting::USER_INPUT_TEXT => '#1b212a',
                WebchatSetting::ICON_BACKGROUND => '0000ff',
                WebchatSetting::ICON_HOVER_BACKGROUND => 'ffffff',
                WebchatSetting::BUTTON_BACKGROUND => '#7fdad1',
                WebchatSetting::BUTTON_HOVER_BACKGROUND => '#7fdad1',
                WebchatSetting::BUTTON_TEXT => '#1b2956',
                WebchatSetting::EXTERNAL_BUTTON_BACKGROUND => '#7fdad1',
                WebchatSetting::EXTERNAL_BUTTON_HOVER_BACKGROUND => '#7fdad1',
                WebchatSetting::EXTERNAL_BUTTON_TEXT => '#1b2956',
            ],
            WebchatSetting::WEBCHAT_HISTORY => [
                WebchatSetting::SHOW_HISTORY => true,
                WebchatSetting::NUMBER_OF_MESSAGES => 10,
            ],
            WebchatSetting::COMMENTS => [
                WebchatSetting::COMMENTS_ENABLED => false,
                WebchatSetting::COMMENTS_NAME => 'Comments',
                WebchatSetting::COMMENTS_ENABLED_PATH_PATTERN => '^\\/home\\/posts',
                WebchatSetting::COMMENTS_ENTITY_NAME => 'comments',
                WebchatSetting::COMMENTS_CREATED_FIELDNAME => 'created-at',
                WebchatSetting::COMMENTS_TEXT_FIELDNAME => 'comment',
                WebchatSetting::COMMENTS_AUTHOR_ENTITY_NAME => 'users',
                WebchatSetting::COMMENTS_AUTHOR_RELATIONSHIP_NAME => 'author',
                WebchatSetting::COMMENTS_AUTHOR_ID_FIELDNAME => 'id',
                WebchatSetting::COMMENTS_AUTHOR_NAME_FIELDNAME => 'name',
                WebchatSetting::COMMENTS_SECTION_ENTITY_NAME => 'posts',
                WebchatSetting::COMMENTS_SECTION_RELATIONSHIP_NAME => 'post',
                WebchatSetting::COMMENTS_SECTION_ID_FIELDNAME => 'id',
                WebchatSetting::COMMENTS_SECTION_NAME_FIELDNAME => 'name',
                WebchatSetting::COMMENTS_SECTION_FILTER_PATH_PATTERN => 'home\\/posts\\/(\\d*)\\/?',
                WebchatSetting::COMMENTS_SECTION_FILTER_QUERY => 'post',
                WebchatSetting::COMMENTS_SECTION_PATH_PATTERN => 'home\\/posts\\/\\d*$',
                WebchatSetting::COMMENTS_ENDPOINT => "$commentsUrl/json-api/v1",
                WebchatSetting::COMMENTS_AUTH_TOKEN => "Bearer $token",
            ],
        ];
    }
}
