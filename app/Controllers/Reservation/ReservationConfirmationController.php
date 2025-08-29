<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Event\EventInscriptionDatesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\TarifsRepository;
use app\Services\ReservationSessionService;
use app\Repository\Event\EventsRepository;
use app\Utils\ReservationContextHelper;
use DateInterval;
use DateTime;


#[Route('/reservation/confirmation', name: 'app_reservation_confirmation')]
class ReservationConfirmationController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // route publique
    }

    public function index(): void
    {
        $session = $_SESSION['reservation'][session_id()] ?? [];
        $eventsRepository = new \app\Repository\Event\EventsRepository();
        $tarifsRepository = new \app\Repository\TarifsRepository();
        $nageusesRepository = new \app\Repository\Nageuse\NageusesRepository();

        $event = null;
        $sessionObj = null;
        $nageuse = null;
        $tarifs = [];
        $tarifsById = [];

        if (!empty($session['event_id'])) {
            $event = $eventsRepository->findById($session['event_id']);
            if ($event && !empty($session['event_session_id'])) {
                foreach ($event->getSessions() as $s) {
                    if ($s->getId() == $session['event_session_id']) {
                        $sessionObj = $s;
                        break;
                    }
                }
            }
            $tarifs = $tarifsRepository->findByEventId($session['event_id']);
            foreach ($tarifs as $tarif) {
                $tarifsById[$tarif->getId()] = $tarif->getLibelle();
            }
        }
        if (!empty($session['nageuse_id'])) {
            $nageuse = $nageusesRepository->findById($session['nageuse_id']);
        }

        $this->render('reservation/confirmation', [
            'reservation' => $session,
            'event' => $event,
            'session' => $sessionObj,
            'nageuse' => $nageuse,
            'tarifs' => $tarifs,
            'tarifsById' => $tarifsById
        ], 'Réservations');
    }


}