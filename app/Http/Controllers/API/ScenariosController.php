<?php


namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Facades\Serializer;
use App\Http\Requests\ConversationObjectDuplicationRequest;
use App\Http\Requests\ConversationRequest;
use App\Http\Requests\ScenarioCreateRequest;
use App\Http\Requests\ScenarioUpdateRequest;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\ScenarioDeploymentKeyResource;
use App\Http\Resources\ScenarioResource;
use App\Http\Resources\ScenarioResourceCollection;
use App\ScenarioAccessToken;
use App\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use OpenDialogAi\Core\Components\Configuration\ComponentConfiguration;
use OpenDialogAi\Core\Conversation\Conversation;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Facades\ScenarioDataClient;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\ImportExportHelpers\PathSubstitutionHelper;
use OpenDialogAi\Core\ImportExportHelpers\ScenarioImportExportHelper;
use OpenDialogAi\PlatformEngine\Components\WebchatPlatform;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScenariosController extends Controller
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
     * Returns a collection of scenarios.
     *
     * @return ScenarioResourceCollection
     */
    public function index(): ScenarioResourceCollection
    {
        $scenarios = ConversationDataClient::getAllScenarios();
        return new ScenarioResourceCollection($scenarios);
    }

    /**
     * Display the specified scenario.
     *
     * @param Scenario $scenario
     * @return ScenarioResource
     */
    public function show(Scenario $scenario): ScenarioResource
    {
        return new ScenarioResource($scenario);
    }

    /**
     * Display the specified scenario deployment key.
     *
     * @param Scenario $scenario
     * @return ScenarioDeploymentKeyResource
     */
    public function showDeploymentKey(Scenario $scenario): ScenarioDeploymentKeyResource
    {
        $deploymentKey = ScenarioAccessToken::where('scenario_id', $scenario->getUid())->first();
        if (!$deploymentKey) {
            abort(404);
        }
        return new ScenarioDeploymentKeyResource($deploymentKey);
    }

    /**
     * Returns a collection of conversations for a particular scenario.
     *
     * @param Scenario $scenario
     * @return ConversationResource
     */
    public function showConversationsByScenario(Scenario $scenario): ConversationResource
    {
        $conversations = ConversationDataClient::getAllConversationsByScenario($scenario);
        return new ConversationResource($conversations);
    }

    /**
     * Store a newly created conversation against a particular scenario.
     *
     * @param Scenario $scenario
     * @param ConversationRequest $request
     * @return ConversationResource
     */
    public function storeConversationsAgainstScenario(Scenario $scenario, ConversationRequest $request): ConversationResource
    {
        $newConversation = Serializer::deserialize($request->getContent(), Conversation::class, 'json');
        $newConversation->setScenario($scenario);
        $conversation = ConversationDataClient::addConversation($newConversation);

        return new ConversationResource($conversation);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param ScenarioCreateRequest $request
     * @return ScenarioResource|JsonResponse
     */
    public function store(ScenarioCreateRequest $request)
    {
        switch ($request->query('creation_type', 'default')) {
            case 'duplicate':
                // Duplicate
                $scenario = ConversationDataClient::getScenarioByUid($request->query('object_id'));
                return $this->duplicateScenario($scenario, $request)->response()->setStatusCode(201);
            case 'from-template':
                // From template
                $template = Template::find($request->query('object_id'));
                return $this->createScenarioFromTemplate($template, $request)->response()->setStatusCode(201);
            case 'default':
            default:
                // Create a default scenario for a platform
                $platformId = $request->query('object_id', WebchatPlatform::getComponentId());
                return $this->createDefaultScenarioForPlatform($platformId, $request)->response()->setStatusCode(201);
        }
    }

    /**
     * Update the specified scenario.
     *
     * @param ScenarioUpdateRequest $request
     * @param Scenario $scenario
     * @return ScenarioResource
     */
    public function update(ScenarioUpdateRequest $request, Scenario $scenario): ScenarioResource
    {
        $scenarioUpdate = Serializer::deserialize($request->getContent(), Scenario::class, 'json');
        $updatedScenario = ConversationDataClient::updateScenario($scenarioUpdate);
        return new ScenarioResource($updatedScenario);
    }

    /**
     * Destroy the specified scenario.
     *
     * @param Scenario $scenario
     * @return Response $response
     */
    public function destroy(Scenario $scenario): Response
    {
        if (ConversationDataClient::deleteScenarioByUid($scenario->getUid())) {
            ComponentConfiguration::where([
                'scenario_id' => $scenario->getUid()
            ])->delete();

            return response()->noContent(200);
        } else {
            return response('Error deleting scenario, check the logs', 500);
        }
    }

    /**
     * @param ConversationObjectDuplicationRequest $request
     * @param Scenario|null $scenario
     * @param Template|null $template
     * @return ScenarioResource|Response
     * @deprecated Use store instead
     */
    public function duplicate(
        ConversationObjectDuplicationRequest $request,
        Scenario $scenario = null,
        Template $template = null
    ) {
        if (!is_null($template)) {
            // Creating from template
            return $this->createScenarioFromTemplate($template, $request);
        } elseif (!is_null($scenario)) {
            // Duplicating from scenario
            return $this->duplicateScenario($scenario, $request);
        } else {
            return response(null, 400);
        }
    }

    public function export(Scenario $scenario): StreamedResponse
    {
        $scenario = ScenarioDataClient::getFullScenarioGraph($scenario->getUid());
        $data = json_decode(ScenarioImportExportHelper::getSerializedData($scenario), true);

        $odId = $scenario->getOdId();
        $fileName = ScenarioImportExportHelper::suffixScenarioFileName($odId);

        return response()->streamDownload(
            fn () => print(json_encode($data)),
            $fileName,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * @param string $platformId
     * @param Request $request
     * @return ScenarioResource
     */
    protected function createDefaultScenarioForPlatform(string $platformId, Request $request)
    {
        $data = $this->getCreateDefaultScenarioForPlatformData($platformId, $request);
        $scenario = ScenarioImportExportHelper::importScenarioFromString(json_encode($data));

        return new ScenarioResource($scenario);
    }

    /**
     * @param Scenario $scenario
     * @param Request $request
     * @return ScenarioResource
     */
    protected function duplicateScenario(Scenario $scenario, Request $request): ScenarioResource
    {
        $scenario = ScenarioDataClient::getFullScenarioGraph($scenario->getUid());
        $scenario = ConversationObjectDuplicationRequest::setUniqueOdId($scenario, $request);
        $data = json_decode(ScenarioImportExportHelper::getSerializedData($scenario), true);

        $scenario = ScenarioImportExportHelper::importScenarioFromString(json_encode($data));

        return new ScenarioResource($scenario);
    }

    /**
     * @param Template $template
     * @param Request $request
     * @return ScenarioResource
     */
    protected function createScenarioFromTemplate(Template $template, Request $request): ScenarioResource
    {
        $data = $this->getCreateFromTemplateScenarioData($template, $request);
        $scenario = ScenarioImportExportHelper::importScenarioFromString(json_encode($data));

        return new ScenarioResource($scenario);
    }

    /**
     * @param string $platformId
     * @param Request $request
     * @return array
     */
    protected function getCreateDefaultScenarioForPlatformData(string $platformId, Request $request)
    {
        switch ($platformId) {
            case WebchatPlatform::getComponentId():
            default:
                $data = json_decode(File::get(resource_path('platform-defaults/webchat.json')), true);
        }

        return $this->prepareScenarioDataWithRequest($data, $request);
    }

    /**
     * @param Template $template
     * @param Request $request
     * @return array
     */
    protected function getCreateFromTemplateScenarioData(Template $template, Request $request)
    {
        $data = $template->data;
        return $this->prepareScenarioDataWithRequest($data, $request);
    }

    /**
     * @param array $data
     * @param Request $request
     * @return mixed
     */
    protected function prepareScenarioDataWithRequest(array $data, Request $request)
    {
        $originalTemplateOdId = $data['od_id'];

        $tempScenario = new Scenario();
        $tempScenario->setOdId($originalTemplateOdId);
        $tempScenario->setName($data['name']);

        if (isset($data['description'])) {
            $tempScenario->setDescription($data['description']);
        }

        $tempScenario = ConversationObjectDuplicationRequest::setUniqueOdId($tempScenario, $request, null, false, true);
        $tempScenario = ConversationObjectDuplicationRequest::setDescription($tempScenario, $request);

        $data['od_id'] = $tempScenario->getOdId();
        $data['name'] = $tempScenario->getName();
        $data['description'] = $tempScenario->getDescription();

        $oldPath = PathSubstitutionHelper::createPath($originalTemplateOdId);
        $newPath = PathSubstitutionHelper::createPath($tempScenario->getOdId());

        return json_decode(str_replace($oldPath, $newPath, json_encode($data)), true);
    }
}
