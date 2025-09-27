<?php

namespace app\Services\Log;

use app\Enums\LogType;
use app\Repository\Log\LogRepository;

class LogQueryService
{
    private LogRepository $logRepository;
    private const int LOGS_PER_PAGE = 50;

    public function __construct(LogRepository $logRepository)
    {
        $this->logRepository = $logRepository;
    }

    /**
     * Récupère les logs de manière paginée en fonction des filtres fournis.
     *
     * @param array $queryParams Les paramètres de la requête GET.
     * @return array Un tableau contenant les données pour la vue (logs, pagination, filtres).
     */
    public function getPaginatedLogs(array $queryParams): array
    {
        // Valider et nettoyer les filtres
        $filters = [
            'level'   => !empty($queryParams['level']) ? strtoupper(htmlspecialchars($queryParams['level'])) : null,
            'channel' => !empty($queryParams['channel']) ? strtolower(htmlspecialchars($queryParams['channel'])) : null,
            'ip'      => !empty($queryParams['ip']) && filter_var($queryParams['ip'], FILTER_VALIDATE_IP) ? $queryParams['ip'] : null,
            'user'    => !empty($queryParams['user']) ? htmlspecialchars($queryParams['user']) : null,
        ];

        // Gérer la pagination
        $currentPage = max(1, (int)($queryParams['page'] ?? 1));
        $offset = ($currentPage - 1) * self::LOGS_PER_PAGE;

        // Appeler le Repository
        $totalLogs = $this->logRepository->count($filters);
        $logs = $this->logRepository->search($filters, self::LOGS_PER_PAGE, $offset);

        // Calculer les informations de pagination
        $totalPages = (int)ceil($totalLogs / self::LOGS_PER_PAGE);

        // Construire et trier la liste des channels à partir de l'Enum
        $channels = array_map(fn(LogType $c) => $c->value, LogType::cases());

        if (class_exists(\Collator::class)) {
            $collator = new \Collator('fr_FR');
            usort($channels, fn(string $a, string $b) => $collator->compare($a, $b));
        } else {
            // Fallback sans intl: tri naturel, insensible à la casse
            sort($channels, SORT_NATURAL | SORT_FLAG_CASE);
        }

        // Retourner un objet de résultat structuré
        return [
            'logs' => $logs,
            'pagination' => [
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'totalResults' => $totalLogs,
                'perPage' => self::LOGS_PER_PAGE,
            ],
            'filters' => $filters,
            'logLevels' => ['DEBUG', 'INFO', 'NOTICE', 'WARNING', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'],
            'logChannels' => $channels,
        ];
    }
}