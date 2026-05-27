<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Log\Logger;
use app\Services\Reservation\ReservationEntranceAccessService;
use app\Services\Reservation\ReservationQueryService;

class ReservationEntranceController extends AbstractController
{
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;
    private EventQueryService $eventQueryService;
    private ReservationEntranceAccessService $reservationEntranceAccessService;
    private int $delayIsComing = 10800; // 10800 = 3h

    public function __construct(
        ReservationRepository            $reservationRepository,
        ReservationQueryService          $reservationQueryService,
        EventQueryService                $eventQueryService,
        ReservationEntranceAccessService $reservationEntranceAccessService,
    ) {
        parent::__construct(false);
        $this->reservationRepository =              $reservationRepository;
        $this->reservationQueryService =            $reservationQueryService;
        $this->eventQueryService =                  $eventQueryService;
        $this->reservationEntranceAccessService =   $reservationEntranceAccessService;
    }

    /**
     * @return void
     */
    #[Route('/entrance', name: 'app_entrance', methods: ['GET'])]
    public function reservationEntrance(): void
    {
        $reservationToken = (string)($_GET['token'] ?? '');
        if (empty($reservationToken)) {
            $this->render('errors/404', [], 'Accès refusé');
            return;
        }

        $reservation = $this->reservationRepository->findByField('token', $reservationToken, true, true, false);



        // Vérification de l'accès temporel
        $accessCheck = $this->reservationEntranceAccessService->canModifyReservation($reservation);

        $this->render('/entrance/entrance', [
            'reservation' => $reservation,
            'everyOneIsPresent' => $accessCheck['everyOneInReservation'] ?? null,
            'canModify' => $accessCheck['allowed'],
            'accessMessage' => $accessCheck['message'] ?? null,
            'availableAt' => $accessCheck['availableAt'] ?? null,
        ], 'Réservations');
    }

    #[Route('/entrance/scan', name: 'app_entrance_scan', methods: ['GET'])]
    public function reservationEntranceScan(): void
    {
        $this->render('/entrance/entrance-scanner', [
            'searchQuery' => '',
        ], 'Réservations');
    }

    /**
     * @return void
     */
    #[Route('/entrance/search', name: 'app_entrance_search', methods: ['GET'])]
    public function reservationEntranceSearch(): void
    {
        $searchQuery = $_GET['q'] ?? '';
        $trimmedSearchQuery = trim($searchQuery);

        // Récupérer les sessions du jour dans tous les cas
        $events = $this->eventQueryService->getAllEventsWithRelations(true, $this->delayIsComing);
        $todaySessions = [];

        if (!empty($events)) {
            $nbSpectatorsPerSession = $this->reservationQueryService->getNbSpectatorsPerSession($events);
            $sessions = $events[0]->getSessions();

            foreach($sessions as $session) {
                $todaySessions[$session->getId()] = [
                    'name' => $session->getSessionName(),
                    'datetime' => $session->getEventStartAt()->format('d/m/Y à H:i'),
                    'total' => $nbSpectatorsPerSession[$session->getId()]['qty'],
                    'entered' => $nbSpectatorsPerSession[$session->getId()]['entered'],
                ];
            }
        }

        if ($trimmedSearchQuery === '') {
            $this->render('/entrance/entrance-search', [
                'searchQuery' => '',
                'reservations' => [],
                'single' => false,
                'todaySessions' => $todaySessions,
                'noUpcomingEvents' => empty($events),
            ], "Recherche d'entrée");
            return;
        }

        if (!$this->isValidEntranceSearchQuery($trimmedSearchQuery)) {
            $this->render('/entrance/entrance-search', [
                'searchQuery' => $trimmedSearchQuery,
                'searchError' => 'Saisissez au moins 2 caractères, ou 1 chiffre pour rechercher un numéro de réservation.',
                'reservations' => [],
                'single' => false,
                'todaySessions' => $todaySessions,
                'noUpcomingEvents' => empty($events),
            ], "Recherche d'entrée");
            return;
        }

        $result = $this->reservationQueryService->searchForEntrance($trimmedSearchQuery);

        if ($result['single'] && !empty($result['reservations'])) {
            $reservation = $result['reservations'][0];
            header('Location: /entrance?token=' . $reservation->getToken());
            exit;
        }

        $this->render('/entrance/entrance-search', [
            'searchQuery' => $trimmedSearchQuery,
            'reservations' => $result['reservations'],
            'single' => $result['single'],
            'todaySessions' => $todaySessions,
        ], "Recherche d'entrée");
    }

    /**
     * Recherche d'entrée : minimum 2 caractères, sauf un seul chiffre pour un id de réservation.
     */
    private function isValidEntranceSearchQuery(string $query): bool
    {
        return preg_match('/^\d$/', $query) === 1 || strlen($query) >= 2;
    }


    /**
     * @param int $id
     * @return void
     */
    #[Route('/entrance/update/{id}', name: 'app_entrance_update', methods: ['POST'])]
    public function reservationEntranceUpdate(int $id): void
    {
        // Vérifier les permissions de l'utilisateur connecté
        $this->checkUserPermission('R');

        $reservation = $this->reservationRepository->findById($id, true, true);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Réservation non trouvée.'], 404);
        }

        // Vérification de l'accès temporel
        $accessCheck = $this->reservationEntranceAccessService->canModifyReservation($reservation);
        if (!$accessCheck['allowed']) {
            $this->json($accessCheck, 403);
        }

        //On récupère les données
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $complement = $data['complement'] ?? null;
        $participant = $data['participant'] ?? null;
        $isPresent = $data['is_present'] ?? null;
        $isChecked = $data['is_checked'] ?? null;

        if ($complement === null && $participant === null && $isChecked === null) {
            $this->json(['success' => false, 'message' => 'Rien à mettre à jour']);
        }

        if ($complement !== null) {
            $this->json(
                $this->reservationEntranceAccessService->checkComplementForEntrance($reservation, $complement, $this->currentUser)
            );
        }

        if ($participant !== null) {
            $this->json(
                $this->reservationEntranceAccessService->checkParticipantForEntrance($reservation, $participant, $this->currentUser, $isPresent)
                );
        }

        if ($isChecked !== null) {
            $this->reservationRepository->updateSingleField($reservation->getId(), 'is_checked', true);
            //On log l'event
            Logger::get()->event(
                'reservation.entrance.checked',
                [
                    'reservation_id' => $reservation->getId(),
                    'entry_validate_by_user_id' => $this->currentUser->getId()
                ]);
            $this->json(['success' => true, 'message' => 'Réservation marquée comme vérifiée']);
        }

        $this->json(['success' => false, 'message' => 'Erreur inconnue']);
    }

}