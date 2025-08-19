<?php
namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\LogRotationService;

class GestionController extends AbstractController
{
    #[Route('/gestion', name: 'app_gestion')]
    public function index(): void
    {
        $logRotationService = new LogRotationService();

        // Effectuer la rotation et récupérer le rapport
        $rotationReport = $logRotationService->rotateAllLogs();

        // Afficher un message flash seulement s'il y a eu des suppressions
        if ($rotationReport['files_deleted'] > 0 || $rotationReport['mongo_docs_deleted'] > 0) {
            $_SESSION['flash_message'] = [
                'type' => 'info',
                'message' => sprintf(
                    'Rotation automatique effectuée : %d fichiers et %d documents supprimés',
                    $rotationReport['files_deleted'],
                    $rotationReport['mongo_docs_deleted']
                )
            ];
        }

        // Récupérer les statistiques pour l'affichage
        $logsStats = $logRotationService->getLogsStatistics();

        $this->render('gestion/gestion', [
            'logsStats' => $logsStats,
            'rotationReport' => $rotationReport
        ]);
    }

    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}