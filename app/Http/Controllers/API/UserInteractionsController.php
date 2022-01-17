<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserInteractionsRequest;
use App\Http\Resources\UserInteractionsResourceCollection;
use Carbon\Carbon;
use OpenDialogAi\ConversationLog\Message;

class UserInteractionsController extends Controller
{
    /**
     * Gets the user interactions json data
     * @param UserInteractionsRequest $request
     * @return UserInteractionsResourceCollection
     */
    public function index(UserInteractionsRequest $request)
    {
        $from = Carbon::parse($request->from);
        $to = Carbon::parse($request->to);

        $messages = Message::whereBetween('created_at', [$from, $to])
            ->where('author', '!=', 'them')
            ->get();

        return new UserInteractionsResourceCollection($from, $to, $messages);
    }
}
