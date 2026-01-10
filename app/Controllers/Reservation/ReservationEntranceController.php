<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Pagination\PaginationService;
use app\Services\Reservation\ReservationQueryService;

class ReservationEntranceController extends AbstractController
{
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;
    private ReservationDetailRepository $reservationDetailRepository;
    private PaginationService $paginationService;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationQueryService $reservationQueryService,
        ReservationDetailRepository $reservationDetailRepository,
        PaginationService $paginationService,
    ) {
        parent::__construct(false);
        $this->reservationRepository = $reservationRepository;
        $this->reservationQueryService = $reservationQueryService;
        $this->reservationDetailRepository = $reservationDetailRepository;
        $this->paginationService = $paginationService;
    }

    #[Route('/entrance', name: 'app_entrance', methods: ['GET'])]
    public function reservationEntrance(): void
    {
        $reservationToken = (string)($_GET['token'] ?? '');
        if (empty($reservationToken)) {
            $this->render('errors/404', [], 'Accès refusé');
            return;
        }

        $reservation = $this->reservationRepository->findByField('token', $reservationToken, true, true, false, true);
        //On compare le nombre de détails avec entered_at == null au nombre de details avec entered_at == not null
        $everyOneInReservation = $this->reservationQueryService->everyOneInReservationIsHere($reservation);

        $this->render('entrance', [
            'reservation' => $reservation,
            'everyOneIsPresent' => $everyOneInReservation,
        ], 'Réservations');
    }

    #[Route('/entrance/search', name: 'app_entrance_search', methods: ['GET'])]
    public function search(): void
    {
        $searchQuery = $_GET['q'] ?? '';

        if (empty(trim($searchQuery))) {
            $this->render('/entrance-search', [
                'searchQuery' => '',
                'reservations' => [],
                'single' => false
            ], "Recherche d'entrée");
            return;
        }

        $result = $this->reservationQueryService->searchForEntrance($searchQuery);

        // Si un seul résultat, redirection automatique
        if ($result['single'] && !empty($result['reservations'])) {
            $reservation = $result['reservations'][0];
            header('Location: /entrance?token=' . $reservation->getToken());
            exit;
        }

        $this->render('/entrance-search', [
            'searchQuery' => $searchQuery,
            'reservations' => $result['reservations'],
            'single' => $result['single']
        ], "Recherche d'entrée");
    }


    #[Route('/entrance/update/{id}', name: 'app_entrance_update', methods: ['POST'])]
    public function reservationEntranceUpdate(int $id): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('R');

        $reservation = $this->reservationRepository->findById($id);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.'], 404);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $complement = $data['complement'] ?? null;
        $participant = $data['participant'] ?? null;
        $isPresent = $data['is_present'] ?? null;
        $isChecked = $data['is_checked'] ?? null;

        if ($complement === null && $participant === null && $isChecked === null) {
            $this->json(['success' => false, 'message' => 'Rien à mettre à jour']);
        }

        if ($complement !== null) {
            $value = $complement ? date('Y-m-d H:i:s') : null;
            $this->reservationRepository->updateSingleField($reservation->getId(), 'complements_given_at', $value);
            $this->reservationRepository->updateSingleField($reservation->getId(), 'complements_given_by', $value == null ? null:$this->currentUser->getId());
            $this->json(['success' => true, 'message' => 'Mise à jour effectuée']);
        }

        if ($participant !== null) {
            $value = $isPresent ? date('Y-m-d H:i:s') : null;
            $this->reservationDetailRepository->updateSingleField($participant, 'entered_at', $value);
            //On compare le nombre de détails avec entered_at == null au nombre de details avec entered_at == not null
            $reservation = $this->reservationRepository->findById($id);
            $everyOneInReservation = $this->reservationQueryService->everyOneInReservationIsHere($reservation);
            $this->json(['success' => true, 'message' => 'Mise à jour effectuée', 'everyOneInReservation' => $everyOneInReservation]);
        }

        if ($isChecked !== null) {
            $this->reservationRepository->updateSingleField($reservation->getId(), 'is_checked', true);
            $this->json(['success' => true, 'message' => 'Réservation marquée comme vérifiée']);
        }

        $this->json(['success' => false, 'message' => 'Erreur inconnue']);
    }

}