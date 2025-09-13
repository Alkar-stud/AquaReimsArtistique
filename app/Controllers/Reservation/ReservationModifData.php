<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventsRepository;
use app\Repository\Reservation\ReservationsRepository;

class ReservationModifData extends AbstractController
{
    private ReservationsRepository $reservationsRepository;
    private EventsRepository $eventsRepository;
    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationsRepository = new ReservationsRepository();
        $this->eventsRepository = new EventsRepository();
    }

    #[Route('/modifData', name: 'app_reservation_modif_data')]
    public function modifData(): void
    {
        $error = null;
        //On récupère le token dans l'URL
        if (isset($_GET['token']) && ctype_alnum($_GET['token'])) {
            $token = $_GET['token'];
        } else {
            $token = null;
            $error = 'Cette réservation n\'existe pas.';
        }

        //On récupère la réservation
        $reservation = $this->reservationsRepository->findByToken($token);
        if (!$reservation) {
            $error = 'Cette réservation n\'existe pas.';
        }

        //On récupère l'événement et la session
        $event = $this->eventsRepository->findById($reservation->getEvent());


        $this->render('reservation/modif_data', [
            'reservation' => $reservation,
            'event' => $event,
            'error' => $error
        ], 'ModifData');
    }

}