<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Anonymize\AnonymizeDataService;
use app\Services\Reservation\ReservationFinalSummaryService;
use Exception;

class CommandsController extends AbstractController
{
    private ReservationFinalSummaryService $reservationFinalSummaryService;

    public function __construct(ReservationFinalSummaryService $reservationFinalSummaryService)
    {
        parent::__construct(false);
        $this->reservationFinalSummaryService = $reservationFinalSummaryService;
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
            [
                'name' => 'Envoi de mails récapitulatif',
                'description' => 'Envoi le mail `final_summary` aux réservations concernées, par paquet de 100 maximum',
                'url' => '/gestion/commands/send-recap-email',
                'icon' => 'bi-send',
                'danger' => true,
                'input' => ['type' => 'number', 'max' => 100, 'value' => 10],
            ],
        ];

        $this->render('/gestion/commands', [
            'commands' => $commands
        ], 'Gestion des commandes');
    }

    #[Route('/gestion/commands/anonymize', name: 'app_gestion_commands_anonymize', methods: ['POST'])]
    public function anonymize(): void
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

    #[Route('/gestion/commands/send-recap-email', name: 'app_gestion_commands_send_recap', methods: ['POST'])]
    public function sendRecapEmail(): void
    {
        $this->checkIfCurrentUserIsAllowedToManagedThis(1);

        // Récupération de la limite depuis le corps de la requête JSON
        $json = json_decode(file_get_contents('php://input'), true);
        $limit = (int)($json['limit'] ?? 100);
        
        $limit = $limit > 0 ? $limit : 100;

        try {
            $result = $this->reservationFinalSummaryService->sendFinalEmail($limit, true);
            
            // On formate les données pour l'affichage en liste dans la modale
            $displayData = [
                'Réservations traitées' => $result['attempted'] ?? 0,
                'Emails envoyés' => $result['sent'] ?? 0,
                'Échecs d\'envoi' => $result['failed'] ?? 0,
            ];

            $this->json(['success' => true, 'data' => $displayData]);
        } catch (Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }

    }

}