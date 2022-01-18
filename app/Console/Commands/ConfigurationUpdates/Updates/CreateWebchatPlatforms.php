<?php

namespace App\Console\Commands\ConfigurationUpdates\Updates;

use App\Console\Commands\ConfigurationUpdates\BaseConfigurationUpdate;
use Illuminate\Support\Facades\DB;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Components\Configuration\ConfigurationDataHelper;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\PlatformEngine\Components\WebchatPlatform;
use OpenDialogAi\Webchat\WebchatSetting;
use OpenDialogAi\Core\ComponentSetting;

class CreateWebchatPlatforms extends BaseConfigurationUpdate
{
    /**
     * @inheritDoc
     */
    public function hasRun(): bool
    {
        $configuration = ComponentConfiguration::where('name', ConfigurationDataHelper::WEBCHAT_PLATFORM)
            ->where('component_id', WebchatPlatform::getComponentId())
            ->first();

        return !is_null($configuration);
    }

    /**
     * @inheritDoc
     */
    public function beforeUp(): bool
    {
        $this->warn("Webchat settings are deprecated, they should now be stored as platform configuration"
            . " for each scenario. Please convert your webchat settings.");

        $updateConfirm = 'Are you sure you want to convert webchat settings to platform configuration?';

        return $this->option('non-interactive') || $this->confirm($updateConfirm);
    }

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $this->createWebchatPlatformForEachScenario();
    }

    /**
     * @inheritDoc
     */
    public function beforeDown(): bool
    {
        $updateConfirm = 'Are you sure you want to convert platform configuration back to webchat settings?';

        return $this->option('non-interactive') || $this->confirm($updateConfirm);
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        ComponentConfiguration::where([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
        ])->delete();
    }

    public function createWebchatPlatformForEachScenario(): void
    {
        ConversationDataClient::getAllScenarios()->each(function (Scenario $scenario) {
            if (self::scenarioHasConfiguration($scenario->getUid(), ConfigurationDataHelper::WEBCHAT_PLATFORM)) {
                $this->info(sprintf(
                    "Skipping: Configuration '%s' already exists for the '%s' component on the '%s' scenario.",
                    ConfigurationDataHelper::WEBCHAT_PLATFORM,
                    WebchatPlatform::getComponentId(),
                    $scenario->getName()
                ));
            } else {
                self::createWebchatPlatformForScenario($scenario->getUid(), $this->getSettings());

                $this->info(sprintf(
                    "Configuration '%s' was created for the '%s' component on the '%s' scenario.",
                    ConfigurationDataHelper::WEBCHAT_PLATFORM,
                    WebchatPlatform::getComponentId(),
                    $scenario->getName()
                ));
            }
        });
    }

    /**
     * @param string $scenarioUid
     * @param array $configuration
     */
    public static function createWebchatPlatformForScenario(string $scenarioUid, array $configuration): void
    {
        ComponentConfiguration::create([
            'name' => ConfigurationDataHelper::WEBCHAT_PLATFORM,
            'scenario_id' => $scenarioUid,
            'component_id' => WebchatPlatform::getComponentId(),
            'configuration' => $configuration,
            'active' => true,
        ]);
    }

    private function convertSettingsToArray(): array
    {
        // Create the config object.
        $config = [];

        // First, get all child settings.
        $parentIds = [];
        $childSettings = ComponentSetting::whereNotNull('parent_id')
            ->where('component_id', WebchatPlatform::getComponentId())->get();
        foreach ($childSettings as $childSetting) {
            if (!in_array($childSetting->parent_id, $parentIds)) {
                $parentIds[] = $childSetting->parent_id;
            }
        }

        // Next, get all top level settings.
        $settings = ComponentSetting::whereNull('parent_id')
            ->where('component_id', WebchatPlatform::getComponentId())->get();

        // Build the config array.
        foreach ($settings as $setting) {
            if (!in_array($setting->id, $parentIds) && !is_null($setting->value)) {
                $value = $this->castValue($setting->type, $setting->value);
                $config[$setting->name] = $value;
            } else {
                foreach ($childSettings as $idx => $childSetting) {
                    if (($childSetting->parent_id == $setting->id) && !is_null($childSetting->value)) {
                        $value = $this->castValue($childSetting->type, $childSetting->value);
                        $config[$setting->name][$childSetting->name] = $value;
                        unset($childSettings[$idx]);
                    }
                }
            }
        }

        return $config;
    }

    /**
     * Handle the incoming request.
     *
     * @param string $type
     * @param string $value
     * @return mixed
     */
    private function castValue($type, $value)
    {
        switch ($type) {
            case 'number':
                $value = (int)$value;
                break;
            case 'boolean':
                $value = boolval($value);
                break;
            case 'map':
            case 'object':
                $value = json_decode($value);
                break;
            default:
                break;
        }

        return $value;
    }

    private function getSettings(): array
    {
        $table = DB::table('component_settings');

        if ($table->exists() && $table->count() > 0 && $table->where('value', '<>', '')->count() > 0) {
            return $this->convertSettingsToArray();
        } else {
            $odUrl = $this->option('url') ? $this->option('url') : env('APP_URL');

            return self::getDefaultSettings($odUrl);
        }
    }

    /**
     * @param string $odUrl
     * @return array
     */
    public static function getDefaultSettings(string $odUrl): array
    {
        $commentsUrl = 'http://example.com';
        $token = 'ApiTokenValue';

        return [
            WebchatSetting::GENERAL => [
                WebchatSetting::URL => "$odUrl/web-chat",
                WebchatSetting::OPEN => true,
                WebchatSetting::TEAM_NAME => "",
                WebchatSetting::LOGO => "$odUrl/images/homepage-logo.svg",
                WebchatSetting::MESSAGE_DELAY => '500',
                WebchatSetting::COLLECT_USER_IP => true,
                WebchatSetting::CHATBOT_AVATAR_PATH => "$odUrl/vendor/webchat/images/avatar.svg",
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
