<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Reservation\ReservationQueryService;
use DateMalformedStringException;
use DateTime;

class ReservationEntranceController extends AbstractController
{
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;
    private ReservationDetailRepository $reservationDetailRepository;
    private EventQueryService $eventQueryService;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationQueryService $reservationQueryService,
        ReservationDetailRepository $reservationDetailRepository,
        EventQueryService $eventQueryService,
    ) {
        parent::__construct(false);
        $this->reservationRepository = $reservationRepository;
        $this->reservationQueryService = $reservationQueryService;
        $this->reservationDetailRepository = $reservationDetailRepository;
        $this->eventQueryService = $eventQueryService;
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
        $accessCheck = $this->canModifyReservation($reservation);
        //On compare le nombre de détails avec entered_at == null au nombre de details avec entered_at == not null
        $everyOneInReservation = $this->reservationQueryService->everyOneInReservationIsHere($reservation);

        $this->render('entrance', [
            'reservation' => $reservation,
            'everyOneIsPresent' => $everyOneInReservation,
            'canModify' => $accessCheck['allowed'],
            'accessMessage' => $accessCheck['message'] ?? null,
            'availableAt' => $accessCheck['availableAt'] ?? null,
        ], 'Réservations');
    }

    /**
     * @return void
     */
    #[Route('/entrance/search', name: 'app_entrance_search', methods: ['GET'])]
    public function search(): void
    {
        $searchQuery = $_GET['q'] ?? '';

        if (empty(trim($searchQuery))) {
            //On récupère les séances du jour avec le nombre de personnes arrivées
            $events = $this->eventQueryService->getAllEventsWithRelations(true);

            //On récupère le nombre de spectateurs par session
            $nbSpectatorsPerSession = $this->reservationQueryService->getNbSpectatorsPerSession($events);
            $sessions = $events[0]->getSessions();

            $todaySessions = [];
            foreach($sessions as $session) {
                $todaySessions[$session->getId()]['name'] = $session->getSessionName();
                $todaySessions[$session->getId()]['total'] = $nbSpectatorsPerSession[$session->getId()]['qty'];
                $todaySessions[$session->getId()]['entered'] = $nbSpectatorsPerSession[$session->getId()]['entered'];

            }

            $this->render('/entrance-search', [
                'searchQuery' => '',
                'reservations' => [],
                'single' => false,
                'todaySessions' => $todaySessions,
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
            'single' => $result['single'],
        ], "Recherche d'entrée");
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
            return;
        }

        // Vérification de l'accès temporel
        $accessCheck = $this->canModifyReservation($reservation);

        if (!$accessCheck['allowed']) {
            $this->json([
                'success' => false,
                'message' => $accessCheck['message'],
                'availableAt' => $accessCheck['availableAt'] ?? null
            ], 403);
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

            $userName = $value !== null ? $this->currentUser->getDisplayName() : null;

            $this->json([
                'success' => true,
                'message' => 'Mise à jour effectuée',
                'complements_given_at' => $value,
                'user_name' => $userName
            ]);
        }

        if ($participant !== null) {
            $value = $isPresent ? date('Y-m-d H:i:s') : null;
            $this->reservationDetailRepository->updateSingleField($participant, 'entered_at', $value);
            $this->reservationDetailRepository->updateSingleField($participant, 'entry_validate_by', $value == null ? null:$this->currentUser->getId());
            //On compare le nombre de détails avec entered_at == null au nombre de details avec entered_at == not null
            $reservation = $this->reservationRepository->findById($id);
            $everyOneInReservation = $this->reservationQueryService->everyOneInReservationIsHere($reservation);

            $userName = $value !== null ? $this->currentUser->getDisplayName() : null;

            $this->json([
                'success' => true,
                'message' => 'Mise à jour effectuée',
                'everyOneInReservation' => $everyOneInReservation,
                'user_name' => $userName
            ]);
        }

        if ($isChecked !== null) {
            $this->reservationRepository->updateSingleField($reservation->getId(), 'is_checked', true);
            $this->json(['success' => true, 'message' => 'Réservation marquée comme vérifiée']);
        }

        $this->json(['success' => false, 'message' => 'Erreur inconnue']);
    }

    /**
     * @param Reservation $reservation
     * @return array|true[]
     */
    private function canModifyReservation(Reservation $reservation): array
    {
        $eventSession = $reservation->getEventSessionObject();
        if (!$eventSession) {
            return ['allowed' => false, 'message' => 'Session non trouvée.'];
        }

        $eventStart = $eventSession->getOpeningDoorsAt();

        $now = new DateTime();
        // Convertir DateTimeInterface en DateTime pour pouvoir le cloner et le modifier
        $eventStartDateTime = DateTime::createFromInterface($eventStart);
        try {
            $twoHoursBefore = (clone $eventStartDateTime)->modify('-2 hours');
        } catch (DateMalformedStringException $e) {

        }

        if ($now < $twoHoursBefore) {
            return [
                'allowed' => false,
                'message' => 'Les modifications ne sont pas encore autorisées. Accessible 2h avant l\'ouverture des portes.',
                'availableAt' => $twoHoursBefore->format('d/m/Y à H:i')
            ];
        }

        return ['allowed' => true];
    }


}