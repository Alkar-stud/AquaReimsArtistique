<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Log\LogQueryService;

class LogsController extends AbstractController
{
    private LogQueryService $logQueryService;

    public function __construct(LogQueryService $logQueryService)
    {
        parent::__construct(false);
        $this->logQueryService = $logQueryService;
    }

    #[Route('/gestion/logs', name: 'app_gestion_logs')]
    public function index(): void
    {
        // 1. Sécurité : seul admin (niveau 1) peut voir les logs.
        $this->checkIfCurrentUserIsAllowedToManagedThis(1, 'gestion');

        // Le service va chercher ce qu'il faut
        $viewData = $this->logQueryService->getPaginatedLogs($_GET);

        // Rendu : on passe les données à la vue.
        $this->render('gestion/logs', $viewData, 'Visualiseur de Logs');
    }
}