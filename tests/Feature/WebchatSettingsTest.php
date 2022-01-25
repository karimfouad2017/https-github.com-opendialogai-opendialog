<?php

namespace Tests\Feature;

use App\User;
use OpenDialogAi\Webchat\WebchatSetting;
use OpenDialogAi\Core\ComponentSetting;
use Tests\TestCase;

class WebchatSettingsTest extends TestCase
{
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();

        // Ensure we start with am empty webchat settings table
        ComponentSetting::truncate();
    }

    public function testWebchatSettingsViewAllEndpoint()
    {
        $this->app['config']->set(
            'opendialog.component_settings.' . WebchatSetting::WEBCHAT_CONFIG,
            [
                WebchatSetting::GENERAL => [
                    WebchatSetting::URL => [
                        WebchatSetting::DISPLAY_NAME => 'URL',
                        WebchatSetting::DISPLAY => false,
                        WebchatSetting::DESCRIPTION => 'The URL the bot is hosted at',
                        WebchatSetting::TYPE => WebchatSetting::STRING,
                    ],
                    WebchatSetting::TEAM_NAME => [
                        WebchatSetting::DISPLAY_NAME => 'Chatbot Name',
                        WebchatSetting::DESCRIPTION => 'The name displayed in the chatbot header',
                        WebchatSetting::TYPE => WebchatSetting::STRING,
                        WebchatSetting::SECTION => "General Settings",
                        WebchatSetting::SUBSECTION => 'Header',
                        WebchatSetting::SIBLING => WebchatSetting::LOGO
                    ],
                    WebchatSetting::LOGO => [
                        WebchatSetting::DISPLAY_NAME => 'Logo',
                        WebchatSetting::DESCRIPTION => 'The chatbot logo displayed in the header',
                        WebchatSetting::TYPE => WebchatSetting::STRING,
                        WebchatSetting::SECTION => "General Settings",
                        WebchatSetting::SUBSECTION => 'Header',
                        WebchatSetting::SIBLING => WebchatSetting::TEAM_NAME
                    ]
                ]
            ]
        );

        $this->artisan('component:settings');

        $response = $this->actingAs($this->user, 'api')
            ->json('GET', '/admin/api/component-setting/platform.core.webchat')
            ->assertStatus(200)
            ->assertJsonFragment(
                [
                    'section' => 'General Settings',
                ]
            )
            ->assertJsonFragment(
                [
                    'subsection' => 'Header',
                ]
            )
            ->assertJsonFragment(
                [
                    'display_name' => 'Logo'
                ]
            )
            ->getContent();

        $this->assertCount(2, json_decode($response, true)[0]['children'][0]['children'][0]);
    }
}
