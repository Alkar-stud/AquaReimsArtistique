<?php
namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Enums\LogType;
use app\Services\Logs\LogService;

class LogsController extends AbstractController
{
    protected LogService $logService;

    public function __construct()
    {
        parent::__construct();
        $this->logService = new LogService();
    }

    #[Route('/gestion/logs', name: 'app_gestion_logs')]
    public function index(): void
    {
        // Paramètres de filtrage
        $type = $_GET['type'] ?? 'all';
        $level = $_GET['level'] ?? 'all'; // Nouveau filtre niveau
        $dateStart = $_GET['date_start'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateEnd = $_GET['date_end'] ?? date('Y-m-d');
        $timeStart = $_GET['time_start'] ?? '00:00';
        $timeEnd = $_GET['time_end'] ?? '23:59';
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 100);
        $excludeLogsRoute = isset($_GET['exclude_logs_route']) ? (bool)$_GET['exclude_logs_route'] : false;

        // Combinaison date + heure et conversion en UTC pour MongoDB
        $localStart = new \DateTime($dateStart . ' ' . $timeStart . ':00', new \DateTimeZone(date_default_timezone_get()));
        $localEnd = new \DateTime($dateEnd . ' ' . $timeEnd . ':59', new \DateTimeZone(date_default_timezone_get()));

        $localStart->setTimezone(new \DateTimeZone('UTC'));
        $localEnd->setTimezone(new \DateTimeZone('UTC'));

        $datetimeStart = $localStart->format('Y-m-d H:i:s');
        $datetimeEnd = $localEnd->format('Y-m-d H:i:s');

        // Récupération des logs avec le nouveau filtre niveau
        $logs = $this->getLogs($type, $level, $datetimeStart, $datetimeEnd, $page, $perPage, $excludeLogsRoute);
        $totalLogs = $this->getTotalLogs($type, $level, $datetimeStart, $datetimeEnd, $excludeLogsRoute);

        // Calcul pagination
        $totalPages = ceil($totalLogs / $perPage);

        // Liste des niveaux disponibles
        $availableLevels = ['INFO', 'APPLICATION', 'WARNING', 'DANGER', 'DEBUG'];

        $this->render('logs/index', [
            'logs' => $logs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'totalLogs' => $totalLogs,
            'filters' => [
                'type' => $type,
                'level' => $level,
                'date_start' => $dateStart,
                'date_end' => $dateEnd,
                'time_start' => $timeStart,
                'time_end' => $timeEnd,
                'exclude_logs_route' => $excludeLogsRoute
            ],
            'logTypes' => LogType::cases(),
            'availableLevels' => $availableLevels
        ], 'Visualisation des logs');

    }

    private function getLogs(string $type, string $level, string $dateStart, string $dateEnd, int $page, int $perPage, bool $excludeLogsRoute): array
    {
        $offset = ($page - 1) * $perPage;

        if ($type === 'all') {
            return $this->logService->getAllLogs($dateStart, $dateEnd, $perPage, $offset, $excludeLogsRoute, $level);
        } else {
            return $this->logService->getLogsByType(LogType::from($type), $dateStart, $dateEnd, $perPage, $offset, $excludeLogsRoute, $level);
        }
    }

    private function getTotalLogs(string $type, string $level, string $dateStart, string $dateEnd, bool $excludeLogsRoute): int
    {
        if ($type === 'all') {
            return $this->logService->countAllLogs($dateStart, $dateEnd, $excludeLogsRoute, $level);
        } else {
            return $this->logService->countLogsByType(LogType::from($type), $dateStart, $dateEnd, $excludeLogsRoute, $level);
        }
    }

    public function getLogTypeBadgeColor(string $type): string
    {
        return match($type) {
            'access' => 'success',
            'database' => 'primary',
            'url' => 'info',
            'application' => 'warning',
            'route' => 'warning',
            'url_error' => 'danger',
            default => 'secondary'
        };
    }

    public function getLogLevelBadgeColor(?string $level): string
    {
        if ($level === null) {
            return 'secondary';
        }

        return match($level) {
            'DANGER' => 'danger',
            'WARNING' => 'warning',
            'APPLICATION' => 'application',
            'INFO' => 'info',
            'DEBUG' => 'secondary',
            default => 'light'
        };
    }

}
