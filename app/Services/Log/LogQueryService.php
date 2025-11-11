<?php

namespace app\Services\Log;

use app\Enums\LogType;
use app\Repository\Log\LogRepository;

class LogQueryService
{
    private LogRepository $logRepository;
    private const int LOGS_PER_PAGE = 50;
    private const LOG_LEVELS_HIERARCHY = [
        'DEBUG'     => 100,
        'INFO'      => 200,
        'NOTICE'    => 250,
        'WARNING'   => 300,
        'ERROR'     => 400,
        'CRITICAL'  => 500,
        'ALERT'     => 550,
        'EMERGENCY' => 600,
    ];

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
        $selectedLevel = !empty($queryParams['level']) ? strtoupper(htmlspecialchars($queryParams['level'])) : null;

        // Préparer le filtre de niveau hiérarchique
        $levelFilter = null;
        if ($selectedLevel && isset(self::LOG_LEVELS_HIERARCHY[$selectedLevel])) {
            $threshold = self::LOG_LEVELS_HIERARCHY[$selectedLevel];
            $levelFilter = [];
            foreach (self::LOG_LEVELS_HIERARCHY as $levelName => $levelValue) {
                if ($levelValue >= $threshold) {
                    $levelFilter[] = $levelName;
                }
            }
        }

        // Valider et nettoyer les filtres
        $repoFilters = [
            'level'   => $levelFilter, // Sera un tableau de niveaux ou null
            'channel' => !empty($queryParams['channel']) ? strtolower(htmlspecialchars($queryParams['channel'])) : null,
            'ip'      => !empty($queryParams['ip']) && filter_var($queryParams['ip'], FILTER_VALIDATE_IP) ? $queryParams['ip'] : null,
            'user'    => !empty($queryParams['user']) ? htmlspecialchars($queryParams['user']) : null,
        ];

        // Gérer la pagination
        $currentPage = max(1, (int)($queryParams['page'] ?? 1));
        $offset = ($currentPage - 1) * self::LOGS_PER_PAGE;

        // Appeler le Repository
        $totalLogs = $this->logRepository->count($repoFilters);
        $logs = $this->logRepository->search($repoFilters, self::LOGS_PER_PAGE, $offset);

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
            'filters' => [
                'level'   => $selectedLevel, // On renvoie le niveau unique sélectionné à la vue
                'channel' => $repoFilters['channel'],
                'ip'      => $repoFilters['ip'],
                'user'    => $repoFilters['user'],
            ],
            'logLevels' => array_keys(self::LOG_LEVELS_HIERARCHY),
            'logChannels' => $channels,
        ];
    }
}