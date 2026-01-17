<?php

namespace app\Commands;

use app\Repository\Event\EventRepository;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationDetailTempRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Mails\MailPrepareService;
use app\Services\Mails\MailService;
use app\Services\Pdf\PdfGenerationService;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Services\Piscine\PiscineQueryService;
use app\Services\Reservation\ReservationFinalSummaryService;
use app\Services\Reservation\ReservationPriceCalculator;
use app\Services\Reservation\ReservationQueryService;
use app\Traits\HasPdoConnection;
use Exception;
use app\Repository\Piscine\PiscineRepository;
use app\Repository\Tarif\TarifRepository;
use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventSessionRepository;


class SendRecapEmailCommand
{
    use HasPdoConnection;

    private ReservationFinalSummaryService $reservationFinalSummaryService;

    public function __construct()
    {
        $this->initPdo();

        // Instanciation des repositories
        $reservationRepository = new ReservationRepository();
        $mailTemplateRepository = new MailTemplateRepository();
        $reservationMailSentRepository = new ReservationMailSentRepository();
        $eventRepository = new EventRepository();
        $piscineRepository = new PiscineRepository();
        $tarifRepository = new TarifRepository();
        $inscriptionDateRepository = new EventInscriptionDateRepository();
        $eventSessionRepository = new EventSessionRepository();
        $reservationComplementRepository = new ReservationComplementRepository();
        $reservationDetailRepository = new ReservationDetailRepository();
        $reservationDetailTempRepository = new ReservationDetailTempRepository();

        // Services de base
        $mailService = new MailService($mailTemplateRepository, $reservationMailSentRepository);
        $mailPrepareService = new MailPrepareService($mailService);

        // PiscineQueryService avec ses 3 dépendances
        $piscineQueryService = new PiscineQueryService();

        // EventQueryService avec ses 6 dépendances
        $eventQueryService = new EventQueryService(
            $eventRepository,
            $piscineRepository,
            $tarifRepository,
            $inscriptionDateRepository,
            $eventSessionRepository,
            $reservationRepository
        );

        // ReservationPriceCalculator
        $priceCalculator = new ReservationPriceCalculator();

        // ReservationQueryService avec ses 8 dépendances
        $reservationQueryService = new ReservationQueryService(
            $reservationRepository,
            $eventRepository,
            $mailPrepareService,
            $priceCalculator,
            $reservationComplementRepository,
            $reservationDetailRepository,
            $reservationDetailTempRepository,
            $piscineQueryService
        );

        // PdfGenerationService avec ses 3 dépendances
        $pdfGenerationService = new PdfGenerationService(
            $eventQueryService,
            $reservationRepository,
            $reservationQueryService
        );

        $this->reservationFinalSummaryService = new ReservationFinalSummaryService(
            $reservationRepository,
            $mailTemplateRepository,
            $mailPrepareService,
            $pdfGenerationService,
            $mailService
        );
    }

    /**
     * Exécute la commande.
     *
     * @param int $limit Nombre maximum d'emails à envoyer
     */
    public function execute(int $limit = 100, $HOST_SITE = ''): int
    {
        // Définir la constante URL_SITE si elle n'existe pas et que $URL_SITE n'est pas vide
        if (!empty($HOST_SITE) && !defined('HOST_SITE')) {
            define('HOST_SITE', $HOST_SITE);
        }

        echo "Début de l'envoi des emails récapitulatifs...\n";
        echo "Limite d'envoi : $limit\n";

        try {
            $result = $this->reservationFinalSummaryService->sendFinalEmail($limit, true);

            echo "Nombre d'emails envoyés : " . ($result['sent'] ?? 0) . "\n";
            echo "Nombre d'erreurs : " . ($result['failed'] ?? 0) . "\n";
            echo "Processus terminé avec succès.\n";
            return 0;
        } catch (Exception $e) {
            echo "ERREUR : " . $e->getMessage() . "\n";
            return 1;
        }
    }
}
