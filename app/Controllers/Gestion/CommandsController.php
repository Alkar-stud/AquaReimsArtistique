<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;

class CommandsController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(false);
    }

    #[Route('/gestion/commands', name: 'app_gestion_commands')]
    public function index(): void
    {
        $this->checkIfCurrentUserIsAllowedToManagedThis(1, 'commands');

        // Calcul de la date limite pour l'anonymisation
        $retentionPeriod = $_ENV['PERSONAL_DATA_RETENTION_DAYS'] ?? 'P3Y';
        try {
            $thresholdDate = (new \DateTime())->sub(new \DateInterval($retentionPeriod));
            $thresholdFormatted = $thresholdDate->format('d/m/Y à H:i');
        } catch (\Exception $e) {
            $thresholdFormatted = 'Date invalide';
        }

        $commands = [
            [
                'name' => 'Anonymisation des données',
                'description' => 'Anonymise les données personnelles antérieures à la période de rétention configurée',
                'url' => '/gestion/commands/anonymize',
                'icon' => 'bi-shield-lock',
                'danger' => false,
                'info' => "Période de rétention : <strong>{$retentionPeriod}</strong><br>Données concernées : commandes reçues avant le <strong>{$thresholdFormatted}</strong>"
            ],
        ];

        $this->render('/gestion/commands', [
            'commands' => $commands
        ], 'Gestion des commandes');
    }

}
