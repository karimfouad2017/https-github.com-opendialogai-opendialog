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
            ->json('POST', '/admin/api/refresh-token', [],
                [
                    'Authorization' => 'Bearer ' . $this->user->api_token
                ]);

        $response->assertStatus(200);

        $newToken = $response->json('api_token');
        $this->assertEquals($newToken, $this->user->api_token);

        // Retry with the new generated token
        $this->actingAs($this->user, 'api')
            ->json('POST', '/admin/api/refresh-token', [],
                [
                    'Authorization' => 'Bearer ' . $this->user->api_token
                ])->assertStatus(200);
    }
}
