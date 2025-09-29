<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Event\EventQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;

class ReservationController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private ReservationSessionService $reservationSessionService;
    private SwimmerQueryService $swimmerQueryService;


    public function __construct(
        EventQueryService $eventQueryService,
        ReservationSessionService $reservationSessionService,
        SwimmerQueryService $swimmerQueryService
    )
    {
        // On déclare la route comme publique pour éviter la redirection vers la page de login.
        parent::__construct(true);
        $this->eventQueryService = $eventQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->swimmerQueryService = $swimmerQueryService;
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




}