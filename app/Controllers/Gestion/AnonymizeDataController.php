<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Anonymize\AnonymizeDataService;
use Exception;

class AnonymizeDataController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(false);
    }

    #[Route('/gestion/commands/anonymize', name: 'app_gestion_commands_anonymize', methods: ['POST'])]
    public function execute(): void
    {
        $this->checkIfCurrentUserIsAllowedToManagedThis(1);

        try {
            $retentionPeriod = $_ENV['PERSONAL_DATA_RETENTION_DAYS'] ?? 'P3Y';
            $anonymizer = new AnonymizeDataService($retentionPeriod);
            $result = $anonymizer->run();

            $this->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
