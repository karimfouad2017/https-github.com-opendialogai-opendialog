<?php

namespace Tests\Feature;

use App\User;
use Tests\TestCase;

class RefreshAPITokenTest extends TestCase
{
    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = factory(User::class)->create();
    }

    public function testRefreshTokenSuccess()
    {
        $response = $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/refresh-token',
                [
                    'user_id' => $this->user->id
                ],
                [
                    'Authorization' => 'Bearer ' . $this->user->api_token
                ]);

        $response->assertStatus(200);

        $newToken = $response->json('api_token');
        $this->assertNotEquals($newToken, $this->user->api_token);

        $this->user->refresh();
        $this->assertEquals($newToken, $this->user->api_token);

        // Retry with the new generated token
        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/refresh-token',
                [
                    'user_id' => $this->user->id
                ],
                [
                    'Authorization' => 'Bearer ' . $this->user->api_token
                ])->assertStatus(200);
    }

    public function testRefreshTokenFailInvalidUserId()
    {
        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/refresh-token', [
                'user_id' => 'Some Id'
            ], [
                'Authorization' => 'Bearer ' . $this->user->api_token
            ])->assertStatus(422);
    }

    public function testRefreshTokenFailInvalidToken()
    {
        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/refresh-token',
                [
                    'user_id' => $this->user->id
                ],
                [
                    'Authorization' => 'Bearer ' . 'Some token'
                ])->assertStatus(422);
    }
}
