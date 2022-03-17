<?php


namespace App\ImportExportHelpers;

use App\Console\Facades\ImportExportSerializer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Components\Configuration\ConfigurationDataHelper;
use OpenDialogAi\Core\Conversation\Conversation;
use OpenDialogAi\Core\Conversation\ConversationCollection;
use OpenDialogAi\Core\Conversation\DataClients\Serializers\Normalizers\ImportExport\ScenarioNormalizer;
use OpenDialogAi\Core\Conversation\Exceptions\DuplicateConversationObjectOdIdException;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Facades\ScenarioDataClient;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Conversation\IntentCollection;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\Conversation\Scene;
use OpenDialogAi\Core\Conversation\Turn;
use OpenDialogAi\Core\InterpreterEngine\OpenDialog\OpenDialogInterpreterConfiguration;
use OpenDialogAi\InterpreterEngine\Interpreters\OpenDialogInterpreter;
use OpenDialogAi\PlatformEngine\Components\WebchatPlatform;
use OpenDialogAi\Webchat\WebchatSetting;

class ScenarioImportExportHelper extends BaseImportExportHelper
{
    const SCENARIO_RESOURCE_DIRECTORY = 'scenarios';
    const SCENARIO_FILE_EXTENSION = ".scenario.json";

    const CONFIGURATIONS = 'configurations';

    /**
     * @return string
     */
    public static function getScenariosPath(): string
    {
        return self::SCENARIO_RESOURCE_DIRECTORY;
    }

    /**
     * @param  string  $name
     *
     * @return string
     */
    public static function suffixScenarioFileName(string $name): string
    {
        return $name.self::SCENARIO_FILE_EXTENSION;
    }

    /**
     * @param  string  $fileName
     *
     * @return string
     */
    public static function prefixScenariosPath(string $fileName): string
    {
        return self::getScenariosPath()."/$fileName";
    }

    public static function getScenarioFilePath(string $odId): string
    {
        return self::prefixScenariosPath(self::suffixScenarioFileName($odId));
    }

    /**
     * @param  string  $filePath
     * @param  string  $data
     */
    public static function createScenarioFile(string $filePath, string $data): void
    {
        self::getDisk()->put($filePath, $data);
    }

    /**
     * @param  string  $filePath
     */
    public static function deleteScenarioFile(string $filePath): void
    {
        self::getDisk()->delete($filePath);
    }

    /**
     * @return array|false
     */
    public static function getScenarioFiles()
    {
        $files = self::getDisk()->files(self::getScenariosPath());
        return preg_grep('/^([^.])/', $files);
    }


    /**
     * Read a scenario file
     *
     * @param  string  $filePath
     *
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public static function getScenarioFileData(string $filePath)
    {
        return self::getDisk()->get($filePath);
    }

    public static function scenarioFileExists(string $filePath)
    {
        return self::getDisk()->exists($filePath);
    }

    public static function getSerializedData(Scenario $scenario): string
    {
        $uidMap = PathSubstitutionHelper::createScenarioMap($scenario);

        $serialized = ImportExportSerializer::serialize($scenario, 'json', [
            ScenarioNormalizer::UID_MAP => $uidMap
        ]);

        $uid = $scenario->getUid();
        if ($uid) {
            /** @var Collection $configurations */
            $configurations = ComponentConfiguration::select([
                'name',
                'scenario_id',
                'component_id',
                'configuration',
                'active',
            ])
                ->where(['scenario_id' => $uid])
                ->orderBy('created_at', 'ASC')
                ->get();

            $configurations->each(function (ComponentConfiguration $c) use ($uidMap) {
                $c->scenario_id = $uidMap->get($c->scenario_id, $c->scenario_id);
            });

            $serializedArray = json_decode($serialized, true);
            $serializedArray[self::CONFIGURATIONS] = $configurations->toArray();
            $serialized = json_encode($serializedArray);
        }

        return $serialized;
    }

    /**
     * @param  string  $data
     *
     * @return Scenario
     */
    public static function importScenarioFromString(string $data): Scenario
    {
        $hasPathsToSubstitute = PathSubstitutionHelper::stringContainsPaths($data);

        $serializerContext = [];

        if ($hasPathsToSubstitute) {
            $serializerContext = [
                ScenarioNormalizer::IGNORE_OBJECTS_WITH_POTENTIAL_PATH_VALUES => true,
            ];
        }

        /* @var $importingScenario Scenario */
        $importingScenario = ImportExportSerializer::deserialize($data, Scenario::class, 'json', $serializerContext);

        $existingScenarios = ConversationDataClient::getAllScenarios();

        $duplicateScenarios = $existingScenarios->filter(
            fn ($scenario) => $scenario->getOdId() === $importingScenario->getOdId()
        );
        if ($duplicateScenarios->count() > 0) {
            throw new DuplicateConversationObjectOdIdException(
                $importingScenario->getOdId(),
                sprintf(
                    "Cannot import scenario with odId %s. A scenario with that odId already exists!",
                    $importingScenario->getOdId()
                )
            );
        }

        $persistedScenario = ScenarioDataClient::addFullScenarioGraph($importingScenario);
        $persistedScenario = ScenarioDataClient::getFullScenarioGraph($persistedScenario->getUid());

        $map = PathSubstitutionHelper::createScenarioMap($persistedScenario);

        if ($hasPathsToSubstitute) {
            // Deserialize WITH objects with potential path values and substitute the paths for the UIDs
            /** @var Scenario $scenarioWithPathsSubstituted */
            $scenarioWithPathsSubstituted = ImportExportSerializer::deserialize($data, Scenario::class, 'json', [
                ScenarioNormalizer::UID_MAP => $map
            ]);
        } else {
            $scenarioWithPathsSubstituted = $persistedScenario;
        }

        $dataArray = json_decode($data, true);
        if (!array_key_exists(ScenarioImportExportHelper::CONFIGURATIONS, $dataArray)) {
            self::createDefaultConfigurationsForScenario($scenarioWithPathsSubstituted->getUid());
        } else {
            foreach ($dataArray[ScenarioImportExportHelper::CONFIGURATIONS] as $configurationData) {
                $substitutedScenarioId = $map->get($configurationData['scenario_id'], $configurationData['scenario_id']);
                $configurationData['scenario_id'] = $substitutedScenarioId;
                ComponentConfiguration::create($configurationData);
            }
        }

        return self::patchScenario($persistedScenario, $scenarioWithPathsSubstituted);
    }

    /**
     * @param Scenario $persistedScenario
     * @param Scenario $scenarioWithPathsSubstituted
     * @return Scenario
     */
    public static function patchScenario(Scenario $persistedScenario, Scenario $scenarioWithPathsSubstituted): Scenario
    {
        if (PathSubstitutionHelper::shouldPatch($scenarioWithPathsSubstituted)) {
            $scenarioPatch = PathSubstitutionHelper::createPatch($persistedScenario->getUid(), $scenarioWithPathsSubstituted);
            ConversationDataClient::updateScenario($scenarioPatch);
        }

        $patchCollection = new ConversationCollection();
        foreach ($scenarioWithPathsSubstituted->getConversations() as $conversationWithPathsSubstituted) {
//            /** @var Conversation $conversationWithPathsSubstituted */
//
//            /** @var Conversation $persistedConversation */
//            $persistedConversation = $persistedScenario->getConversations()
//                ->getObjectsWithId($conversationWithPathsSubstituted->getOdId())->first();
//
//            self::patchConversation($persistedConversation, $conversationWithPathsSubstituted);

            $persistedConversation = $persistedScenario->getConversations()
                ->getObjectsWithId($conversationWithPathsSubstituted->getOdId())->first();
            if (PathSubstitutionHelper::shouldPatch($conversationWithPathsSubstituted)) {
                $conversationPatch = PathSubstitutionHelper::createPatch(
                    $persistedConversation->getUid(),
                    $conversationWithPathsSubstituted
                );
                $patchCollection->addObject($conversationPatch);
            }
        }
        ConversationDataClient::updateConversationBatch($patchCollection);
        $intentPatches = [];
        foreach ($scenarioWithPathsSubstituted->getConversations() as $conversationWithPathsSubstituted) {
            /** @var Conversation $persistedConversation */
            $persistedConversation = $persistedScenario->getConversations()
                ->getObjectsWithId($conversationWithPathsSubstituted->getOdId())->first();

            $patches = self::patchConversation($persistedConversation, $conversationWithPathsSubstituted);
            foreach ($patches as $patch) {
                array_push($intentPatches, $patch);
            }
        }
        Log::debug('Patches: ' . json_encode($intentPatches));
        ConversationDataClient::updateIntentBatch($intentPatches);

        return ScenarioDataClient::getFullScenarioGraph($persistedScenario->getUid());
    }

    /**
     * @param Conversation $persistedConversation
     * @param Conversation $conversationWithPathsSubstituted
     */
    public static function patchConversation(
        Conversation $persistedConversation,
        Conversation $conversationWithPathsSubstituted
    ): array {
//        if (PathSubstitutionHelper::shouldPatch($conversationWithPathsSubstituted)) {
//            $conversationPatch = PathSubstitutionHelper::createPatch(
//                $persistedConversation->getUid(),
//                $conversationWithPathsSubstituted
//            );
//            ConversationDataClient::updateConversation($conversationPatch);
//        }

        $intentPatches = [];
        foreach ($conversationWithPathsSubstituted->getScenes() as $sceneWithPathsSubstituted) {
            /** @var Scene $sceneWithPathsSubstituted */

            /** @var Scene $persistedScene */
            $persistedScene = $persistedConversation->getScenes()
                ->getObjectsWithId($sceneWithPathsSubstituted->getOdId())->first();

            $patches = self::patchScene($persistedScene, $sceneWithPathsSubstituted);
            foreach ($patches as $patch) {
                array_push($intentPatches, $patch);
            }
        }
        return $intentPatches;
    }

    /**
     * @param Scene $persistedScene
     * @param Scene $sceneWithPathsSubstituted
     */
    public static function patchScene(Scene $persistedScene, Scene $sceneWithPathsSubstituted): array
    {
        if (PathSubstitutionHelper::shouldPatch($sceneWithPathsSubstituted)) {
            $scenePatch = PathSubstitutionHelper::createPatch($persistedScene->getUid(), $sceneWithPathsSubstituted);
            Log::debug("PatchScene: " . json_encode($scenePatch));
            ConversationDataClient::updateScene($scenePatch);
        }

        $intentPatches = [];
        foreach ($sceneWithPathsSubstituted->getTurns() as $turnWithPathsSubstituted) {
            /** @var Turn $turnWithPathsSubstituted */

            /** @var Turn $persistedTurn */
            $persistedTurn = $persistedScene->getTurns()->getObjectsWithId($turnWithPathsSubstituted->getOdId())->first();

            $patches = self::patchTurn($persistedTurn, $turnWithPathsSubstituted);
            foreach ($patches as $patch) {
                array_push($intentPatches, $patch);
            }
        }
//        Log::debug('Patches: ' . json_encode($intentPatches));
//        ConversationDataClient::updateIntentBatch($intentPatches);
        return $intentPatches;
    }

    /**
     * @param Turn $persistedTurn
     * @param Turn $turnWithPathsSubstituted
     */
    public static function patchTurn(Turn $persistedTurn, Turn $turnWithPathsSubstituted): array
    {
        if (PathSubstitutionHelper::shouldPatch($turnWithPathsSubstituted)) {
            $turnPatch = PathSubstitutionHelper::createPatch($persistedTurn->getUid(), $turnWithPathsSubstituted);
            Log::debug("PatchTurn: " . json_encode($turnPatch));
            ConversationDataClient::updateTurn($turnPatch);
        }

        $intentPatches = [];
        foreach ($turnWithPathsSubstituted->getRequestIntents() as $iIdx => $intent) {
            /** @var Intent $intent */

            /** @var Intent $persistedIntent */
            $persistedIntent = $persistedTurn->getRequestIntents()[$iIdx];

            if (PathSubstitutionHelper::shouldPatch($intent)) {
                $intentPatch = PathSubstitutionHelper::createPatch($persistedIntent->getUid(), $intent);
//                ConversationDataClient::updateIntent($intentPatch);
                $intentPatches[] = $intentPatch;
            }
        }

        foreach ($turnWithPathsSubstituted->getResponseIntents() as $iIdx => $intent) {
            /** @var Turn $intent */

            /** @var Intent $persistedIntent */
            $persistedIntent = $persistedTurn->getResponseIntents()[$iIdx];

            if (PathSubstitutionHelper::shouldPatch($intent)) {
                $intentPatch = PathSubstitutionHelper::createPatch($persistedIntent->getUid(), $intent);
//                ConversationDataClient::updateIntent($intentPatch);
                $intentPatches[] = $intentPatch;
            }
        }
//        ConversationDataClient::updateIntentBatch($intentPatches);
        return $intentPatches;
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
