<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\DTO\ReservationSelectionSessionDTO;
use app\Services\DataValidation\ReservationDataValidationService;
use app\Services\Event\EventQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;

class ReservationController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private SwimmerQueryService $swimmerQueryService;
    private ReservationDataValidationService $reservationDataValidationService;

    public function __construct(
        EventQueryService $eventQueryService,
        ReservationSessionService $reservationSessionService,
        SwimmerQueryService $swimmerQueryService,
        ReservationDataValidationService $reservationDataValidationService,
    )
    {
        // On déclare la route comme publique pour éviter la redirection vers la page de login.
        parent::__construct(true);
        $this->eventQueryService = $eventQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->swimmerQueryService = $swimmerQueryService;
        $this->reservationDataValidationService = $reservationDataValidationService;
    }

    /**
     * Page d'accueil du processus de réservation
     */
    #[Route('/reservation', name: 'app_reservation')]
    public function index(): void
    {
        // On commence une nouvelle session de réservation, on nettoie les anciennes données de $_SESSION.
        $this->reservationSessionService->clearReservationSession();
        // On récupère toutes les données nécessaires pour l'affichage des événements
        $events = $this->eventQueryService->getAllEventsWithRelations(true);

        // On détermine les statuts des périodes d'inscription pour ces événements
        $inscriptionPeriodsStatus = $this->eventQueryService->getEventInscriptionPeriodsStatus($events);

        //À récupérer seulement s'il y a un event qui en a besoin, sinon on envoie un tableau vide
        // On récupère les nageurs triés par groupe.
        $swimmerPerGroup = $this->swimmerQueryService->getSwimmerByGroup();

        // On récupère uniquement les groupes actifs qui ont des nageurs.
        $groupes = $this->swimmerQueryService->getActiveGroupsWithSwimmers(array_keys($swimmerPerGroup));

        $this->render('reservation/etape1', [
            'events' => $events,
            'periodesOuvertes' => $inscriptionPeriodsStatus['periodesOuvertes'],
            'nextPublicOuvertures' => $inscriptionPeriodsStatus['nextPublicOuvertures'],
            'groupes' => $groupes,
            'swimmerPerGroup' => $swimmerPerGroup
        ], 'Réservations');
    }


    #[Route('/reservation/etape2Display', name: 'etape2Display')]
    public function etape2Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationSession();

        //On vérifie si la session est expirée
        if (!$session || $this->reservationSessionService->isReservationSessionExpired($session)) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=1');
        }

        // Construire le DTO à partir de la session (mapping explicite)
        $dto = ReservationSelectionSessionDTO::fromArray([
            'event_id'        => (int)($session['event_id'] ?? 0),
            'event_session_id' => (int)($session['event_session_id'] ?? 0),
            'swimmer_id'      => isset($session['swimmer_id']) ? (int)$session['swimmer_id'] : null,
            'access_code_used'      => isset($session['access_code_used']) ? (string)$session['access_code_used'] : null,
            'limit_per_swimmer'=> isset($session['limit_per_swimmer']) ? (int)$session['limit_per_swimmer'] : null,
        ]);

        // Valider l'étape 1 avec le DTO
        $check = $this->reservationDataValidationService->validateStep1($dto);
        if (!$check['success']) {
            $this->flashMessageService->setFlashMessage('danger', 'Veuillez reprendre le choix de la séance.');
            $this->redirect('/reservation');
        }

        $this->render('reservation/etape2', [
            'event_id' => $dto->eventId
        ], 'Réservations');
    }

}