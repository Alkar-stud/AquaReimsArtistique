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

        $this->render('reservation/confirmation', [
            'reservation' => $_SESSION['reservation'][session_id()]
        ], 'Réservations');
    }


}