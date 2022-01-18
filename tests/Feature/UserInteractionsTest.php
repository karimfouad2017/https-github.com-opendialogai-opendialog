<?php

namespace Tests\Feature;

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Str;
use OpenDialogAi\ConversationLog\ChatbotUser;
use OpenDialogAi\ConversationLog\Message;
use Tests\TestCase;

class UserInteractionsTest extends TestCase
{
    private $message;
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $userId = Str::random(20);

        $data = [
            'user_id' => $userId,
            'message' => 'message',
            'scenario_id' => 'scenario id',
            'scenario_name' => 'scenario_name',
            'conversation_id' => 'conversation_id',
            'conversation_name' => 'conversation_name',
            'scene_id' => 'scene id',
            'scene_name' => 'scene name',
            'turn_id' => 'turn id',
            'turn_name' => 'turn name',
            'intent_id' => 'intent_id',
            'intent_name' => 'intent name',
            'author' => $userId,
            'data' => [
                'name' => 'name',
                'callback_id' => 'callback_id'
            ]
        ];
        $this->message = $this->createMessageFromData($data);

        $this->user = factory(User::class)->create();
    }

    public function testGetUserInteractionsSuccess()
    {
        $from = date('Y-m-d') . ' 00:00:00';
        $to = date('Y-m-d') . ' 23:59:59';
        $url = '/api/user-interactions/' . $from . '/' . $to;
        $this->actingAs($this->user, 'api')->get($url)
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [array(
                    $this->message->user_id => [
                        'chatbot_user_data',
                        'from',
                        'to',
                        'interactions' => array([
                            'type',
                            'date',
                            'text',
                            'data' => [
                                'name',
                                'callback_id'
                            ]
                        ])]
                )]]);
    }

    public function testGetUserInteractionsInvalidParams()
    {
        $url = '/api/user-interactions/from/to';
        $this->actingAs($this->user, 'api')->get($url)->assertStatus(302);
    }

    public function testGetUserInteractionsOutOfRange()
    {
        $from = Carbon::now()->addDays(-1)->format('Y-m-d H:i:s');
        $to =  Carbon::now()->addDays(-1)->format('Y-m-d H:i:s');
        $url = '/api/user-interactions/' . $from . '/' . $to;
        $this->actingAs($this->user, 'api')->get($url)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function testGetUserInteractionsAuthFailure()
    {
        $from = Carbon::now()->addDays(-1)->format('Y-m-d H:i:s');
        $to =  Carbon::now()->addDays(-1)->format('Y-m-d H:i:s');
        $url = '/api/user-interactions/' . $from . '/' . $to;
        $this->followingRedirects()->get($url)->assertViewIs('auth.login');
    }

    private function createMessageFromData($data): Message
    {
        (new ChatbotUser(['user_id' => $data['user_id']]))->save();

        $message = new Message([
            'user_id' => $data['user_id'],
            'author' => $data['author'],
            'message' => $data['message'],
            'message_id' => Str::random(20),
            'type' => 'button',
            'microtime' => date('Y-m-d') . ' 10:35:06.340100',
            'scenario_id' => $data['scenario_id'],
            'scenario_name' => $data['scenario_name'],
            'conversation_id' => $data['conversation_id'],
            'conversation_name' => $data['conversation_name'],
            'scene_id' => $data['scene_id'],
            'scene_name' => $data['scene_name'],
            'turn_id' => $data['turn_id'],
            'turn_name' => $data['turn_name'],
            'intent_id' => $data['intent_id'],
            'intent_name' => $data['intent_name'],
            'data' => serialize($data['data'])
        ]);
        $message->save();
        return $message->refresh();
    }
}
