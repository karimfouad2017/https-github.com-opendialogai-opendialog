<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScenarioOverviewRequest;
use App\Http\Responses\ScenarioOverviewResponse;
use OpenDialogAi\Core\Conversation\Facades\ScenarioDataClient;

class ScenarioOverviewController extends Controller
{
    public function index(ScenarioOverviewRequest $request)
    {
        $scenarioId = $request->get('scenario');
        $level = $request->get('level');
        $include = explode(",", $request->get('include'));
        $exclude = explode(",", $request->get('exclude'));

        $scenario = ScenarioDataClient::getFullScenarioGraph($scenarioId);
        $response = new ScenarioOverviewResponse($scenario, $level, collect($include), collect($exclude));

        return response()->json($response->formatResponse());
    }
}
