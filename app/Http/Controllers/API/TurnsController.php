<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Facades\Serializer;
use App\Http\Requests\TurnIntentRequest;
use App\Http\Requests\TurnRequest;
use App\Http\Resources\TurnIntentResource;
use App\Http\Resources\TurnIntentResourceCollection;
use App\Http\Resources\TurnResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Intent;
use OpenDialogAi\Core\Conversation\Turn;

class TurnsController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Returns a collection of conversations for a particular scenario.
     *
     * @param Turn $turn
     * @return JsonResponse
     */
    public function showTurnIntentsByTurn(Turn $turn): JsonResponse
    {
        $requestIntents = ConversationDataClient::getAllRequestIntentsByTurn($turn, false);
        $responseIntents = ConversationDataClient::getAllResponseIntentsByTurn($turn, false);

        $turnIntents = [];
        foreach ($requestIntents as $intent) {
            $turnIntents[] = new TurnIntentResource($intent, 'REQUEST');
        }
        foreach ($responseIntents as $intent) {
            $turnIntents[] = new TurnIntentResource($intent, 'RESPONSE');
        }
        return response()->json(new TurnIntentResourceCollection($turnIntents));
    }

    /**
     * Store a newly created TurnIntent against a particular Turn.
     *
     * @param  Turn               $turn
     * @param  TurnIntentRequest  $request
     *
     * @return TurnIntentResource
     */
    public function storeTurnIntentAgainstTurn(Turn $turn, TurnIntentRequest $request): TurnIntentResource
    {
        $newIntent = Serializer::denormalize($request->get('intent'), Intent::class, 'json');
        $newIntent->setTurn($turn);
        if ($request->get('order') === 'REQUEST') {
            $savedIntent = ConversationDataClient::addRequestIntent($newIntent);
            return new TurnIntentResource($savedIntent, 'REQUEST');
        } elseif ($request->get('order') === 'RESPONSE') {
            $savedIntent = ConversationDataClient::addResponseIntent($newIntent);
            return new TurnIntentResource($savedIntent, 'RESPONSE');
        }
    }

    /**
     * Display the specified Turn.
     *
     * @param Turn $turn
     * @return TurnResource
     */
    public function show(Turn $turn): TurnResource
    {
        return new TurnResource($turn);
    }

    /**
     * Update the specified TurnRequest.
     *
     * @param TurnRequest $request
     * @param Turn $turn
     * @return TurnResource
     */
    public function update(TurnRequest $request, Turn $turn): TurnResource
    {
        $turnUpdate = Serializer::deserialize($request->getContent(), Turn::class, 'json');
        $updatedTurn = ConversationDataClient::updateTurn($turnUpdate);
        return new TurnResource($updatedTurn);
    }

    /**
     *
     * @param Turn $Turn
     * @return Response $response
     */
    public function destroy(Turn $Turn): Response
    {
        if (ConversationDataClient::deleteTurnByUid($Turn->getUid())) {
            return response()->noContent(200);
        } else {
            return response('Error deleting conversation, check the logs', 500);
        }
    }

    /**
     *
     * @param Turn $turn
     * @param Intent $intent
     * @return TurnIntentResource $response
     */
    public function getTurnIntentByTurnAndIntent(Turn $turn, Intent $intent) : TurnIntentResource
    {
        $turnWithIntent = ConversationDataClient::getTurnWithIntent($turn->getUid(), $intent->getUid());
        if ($turnWithIntent->getRequestIntents()->count() > 0) {
            return new TurnIntentResource($turnWithIntent->getRequestIntents()->first(), 'REQUEST');
        }

        return new TurnIntentResource($turnWithIntent->getResponseIntents()->first(), 'RESPONSE');
    }


    /**
     * @param TurnIntentRequest $request
     * @param Turn $turn
     * @param Intent $intent
     * @return TurnIntentResource
     */
    public function updateTurnIntent(TurnIntentRequest $request, Turn $turn, Intent $intent) : TurnIntentResource
    {
        $patchIntent = Serializer::denormalize($request->get('intent'), Intent::class, 'json');
        $patchIntent->setUid($intent->getUid());
        // First update the intent data
        $updatedIntent = ConversationDataClient::updateIntent($patchIntent);
        $updatedTurnWithIntent =
            ConversationDataClient::updateTurnIntentRelation($turn->getUid(), $intent->getUid(), $request->get('order'));

        if ($updatedTurnWithIntent->getRequestIntents()->count() > 0) {
            return new TurnIntentResource($updatedTurnWithIntent->getRequestIntents()->first(), 'REQUEST');
        } elseif ($updatedTurnWithIntent->getResponseIntents()->count() > 0) {
            return new TurnIntentResource($updatedTurnWithIntent->getResponseIntents()->first(), 'RESPONSE');
        }
    }
}
