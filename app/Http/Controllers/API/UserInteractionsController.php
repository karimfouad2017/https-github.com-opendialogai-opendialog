<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserInteractionsResourceCollection;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenDialogAi\ConversationLog\Message;

class UserInteractionsController extends Controller
{
    public function index(Request $request)
    {
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $messages = Message::whereBetween('created_at', [$from, $to])
            ->where('author', '!=', 'them')
            ->get();
        return new UserInteractionsResourceCollection($from, $to, $messages);
    }
}
