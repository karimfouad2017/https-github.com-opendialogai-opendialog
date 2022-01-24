<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RefreshAPITokenRequest;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class APITokenController extends Controller
{
    /**
     * Refreshes the user token
     *
     * @return \Illuminate\Http\Response
     */
    public function refreshToken(): \Illuminate\Http\Response
    {
        $user = Auth::user();
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
