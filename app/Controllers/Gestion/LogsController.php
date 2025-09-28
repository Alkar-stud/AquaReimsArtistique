<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\User\UserRepository;
use app\Services\Log\LogQueryService;

class LogsController extends AbstractController
{
    private LogQueryService $logQueryService;
    private UserRepository $userRepository;

    public function __construct(LogQueryService $logQueryService, UserRepository $userRepository)
    {
        parent::__construct(false);
        $this->logQueryService = $logQueryService;
        $this->userRepository = $userRepository;
    }

    #[Route('/gestion/logs', name: 'app_gestion_logs')]
    public function index(): void
    {
        // Sécurité : seul admin (niveau 1) peut voir les logs.
        $this->checkIfCurrentUserIsAllowedToManagedThis(1, 'gestion');

        // Le service va chercher ce qu'il faut
        $viewData = $this->logQueryService->getPaginatedLogs($_GET);

        // Enrichir les logs avec les noms d'utilisateurs pour l'affichage
        if (!empty($viewData['logs'])) {
            $userCache = []; // Cache simple pour éviter les requêtes DB répétées
            foreach ($viewData['logs'] as &$log) {
                if (!empty($log['user_id'])) {
                    $userId = (int)$log['user_id'];
                    if (!isset($userCache[$userId])) {
                        $user = $this->userRepository->findById($userId);
                        $userCache[$userId] = $user ? $user->getUsername() : 'Utilisateur inconnu';
                    }
                    $log['username'] = $userCache[$userId];
                }
            }
            unset($log); // Important : détruire la référence
        }

        // Rendu : on passe les données à la vue.
        $this->render('gestion/logs', $viewData, 'Visualiseur de Logs');
    }
}