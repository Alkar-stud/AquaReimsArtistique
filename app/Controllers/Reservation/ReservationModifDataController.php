<?php

namespace app\Controllers\Reservation;


use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Reservation\ReservationQueryService;
use Exception;

class ReservationModifDataController extends AbstractController
{
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationQueryService $reservationQueryService,
    )
    {
        parent::__construct(true); // route publique
        $this->reservationRepository = $reservationRepository;
        $this->reservationQueryService = $reservationQueryService;
    }

    /**
     * Pour afficher le contenu de la réservation
     *
     * @throws Exception
     */
    #[Route('/modifData', name: 'app_reservation_modif_data')]
    public function modifData(): void
    {
        // On récupère et valide le token depuis l'URL
        $token = $_GET['token'] ?? null;
        if (!$token || !ctype_alnum($token)) {
            http_response_code(404);
            throw new Exception('404');
        }

        // On récupère la réservation par son token
        $reservation = $this->reservationRepository->findByField('token', $token, true, true, true);
        if (!$reservation) {
            http_response_code(404);
            throw new Exception('404');
        }

        //On vérifie si c'est encore modifiable (annulation ou date de fin d'inscription dépassée
        $canBeModified = $this->reservationQueryService->checkIfReservationCanBeModified($reservation);

        if (!$canBeModified) {
            $this->flashMessageService->setFlashMessage('danger', 'La modification n\'est plus possible.');
        }

        //On prépare les détails et les compléments pour la vue
        $readyForView = $this->reservationQueryService->prepareReservationDetailsAndComplementsToView($reservation);



        $this->render('reservation/modif_data', [
            'reservation' => $reservation,
            'reservationView' => $readyForView,
            'canBeModified' => $canBeModified,
        ], 'Récapitulatif de la réservation');

    }



}