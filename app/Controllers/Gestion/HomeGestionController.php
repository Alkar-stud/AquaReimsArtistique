<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;

class HomeGestionController extends AbstractController
{
    private ReservationRepository $reservationRepository;
    private EventQueryService $eventQueryService;

    public function __construct(
        ReservationRepository $reservationRepository,
        EventQueryService $eventQueryService,
    )
    {
        parent::__construct(false);
        $this->reservationRepository = $reservationRepository;
        $this->eventQueryService = $eventQueryService;
    }

    #[Route('/gestion', name: 'app_gestion_home')]
    public function index(): void
    {
        //Stats sur les réservations
        $reservationStats = $this->reservationRepository->getUpcomingSessionsStats();

        //Infos sur les events à venir
        $upcomingEvents = $this->eventQueryService->getStructuredUpcomingEventsForDashboard();

        $this->render('/gestion/home', [
            'reservationStats' => $reservationStats,
            'upcomingEvents' => $upcomingEvents,
        ], "Gestion - Accueil");
    }

}