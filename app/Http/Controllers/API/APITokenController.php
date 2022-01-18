<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefreshAPITokenRequest;
use App\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class APITokenController extends Controller
{
    /**
     * Refreshes the user token
     *
     * @param RefreshAPITokenRequest $request
     * @return \Illuminate\Http\Response
     */
    public function refreshToken(RefreshAPITokenRequest $request): \Illuminate\Http\Response
    {
        $user = User::where('id', $request->user_id)->first();
        if (!$user) {
            return response('Invalid user id provided', 422);
        }
        if ($user->api_token != $request->bearerToken()) {
            return response('Invalid token provided', 422);
        }

        $user->api_token = Str::random(60);
        try {
            $user->update();
        } catch (\Exception $e) {
            Log::error(sprintf('Error updating user token - %s', $e->getMessage()));
            return response('Error updating user token', 500);
        }

        return response(['api_token' => $user->api_token]);
    }
}
