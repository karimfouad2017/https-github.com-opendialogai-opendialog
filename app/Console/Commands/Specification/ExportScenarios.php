<?php


namespace App\Console\Commands\Specification;

use Illuminate\Console\Command;
use OpenDialogAi\Core\Conversation\Facades\ConversationDataClient;
use OpenDialogAi\Core\Conversation\Facades\ScenarioDataClient;
use OpenDialogAi\Core\Conversation\Scenario;
use OpenDialogAi\Core\ImportExportHelpers\ScenarioImportExportHelper;

class ExportScenarios extends Command
{
    protected $signature = 'scenarios:export';

    protected $description = 'Exports all scenarios.';

    public function handle()
    {
        $this->info('Beginning scenarios export...');
        $scenarios = ConversationDataClient::getAllScenarios();
        foreach ($scenarios as $scenario) {
            $fullScenarioGraph = ScenarioDataClient::getFullScenarioGraph($scenario->getUid());
            $this->exportScenario($fullScenarioGraph);
        }
        $this->info('Export complete!');
    }

    public function exportScenario(Scenario $fullScenarioGraph)
    {
        $filePath = ScenarioImportExportHelper::getScenarioFilePath($fullScenarioGraph->getOdId());
        $serialized = ScenarioImportExportHelper::getSerializedData($fullScenarioGraph);

        if (ScenarioImportExportHelper::scenarioFileExists($filePath)) {
            $this->info(sprintf("Scenario file at %s already exists. Deleting...", $filePath));
            ScenarioImportExportHelper::deleteScenarioFile($filePath);
        }
        $this->info(sprintf('Exporting scenario \'%s\' to %s.', $fullScenarioGraph->getOdId(), $filePath));
        ScenarioImportExportHelper::createScenarioFile($filePath, $serialized);
    }
}
