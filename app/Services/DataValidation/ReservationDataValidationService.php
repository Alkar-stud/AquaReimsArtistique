<?php
// php
namespace app\Services\DataValidation;

use app\DTO\ReservationSelectionSessionDTO;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;

class ReservationDataValidationService
{
    public function __construct(
        private readonly EventRepository           $eventRepository,
        private readonly EventSessionRepository    $eventSessionRepository,
        private readonly SwimmerRepository         $swimmerRepository,
        private readonly SwimmerQueryService       $swimmerQueryService,
        private readonly ReservationSessionService $reservationSessionService,
    ) {}

    /**
     * Étape 1: valide event, session, nageur ou code d'accès.
     * Retourne ['success'=>bool, 'errors'=>array, 'data'=>array]
     */
    public function validateStep1(ReservationSelectionSessionDTO $dto): array
    {
        $errors = [];

        // 1) Event
        $event = $this->eventRepository->findById($dto->eventId);
        if (!$event) {
            $errors['eventId'] = 'Événement introuvable.';
        }

        // 2) Session
        $session = null;
        if ($dto->eventSessionId <= 0) {
            $errors['eventSessionId'] = 'Session manquante.';
        } else {
            $session = $this->eventSessionRepository->findById($dto->eventSessionId);
            if (!$session) {
                $errors['eventSessionId'] = 'Session introuvable.';
            } elseif ($event && method_exists($session, 'getEventId') && $session->getEventId() !== $event->getId()) {
                $errors['eventSessionId'] = 'La session ne correspond pas à l\'événement.';
            }
        }

        // 3) Nageur ou code d'accès (si limitation active)
        $limit = $event?->getLimitationPerSwimmer();
        $accessCode = $dto->accessCode ? trim($dto->accessCode) : null;

        if ($limit !== null && $dto->swimmerId === null && $accessCode === null) {
            $errors['swimmerOrAccessCode'] = 'Nageuse ou code d\'accès requis pour cet événement.';
        }

        if ($dto->swimmerId !== null) {
            $swimmer = $this->swimmerRepository->findById($dto->swimmerId);
            if (!$swimmer) {
                $errors['swimmerId'] = 'Nageuse invalide.';
            } else {
                // Vérifie la limite par nageur (hors tarifs à code d'accès).
                $limitRes = $this->swimmerQueryService->isSwimmerLimitReached($dto->eventId, $dto->swimmerId);
                if ($limitRes['error']) {
                    $errors['swimmerId'] = $limitRes['error'];
                } elseif (!empty($limitRes['limitReached'])) {
                    $errors['swimmerId'] = 'La limite de spectateurs pour cette nageuse est atteinte.';
                }
                if (array_key_exists('limit', $limitRes) && $limitRes['limit'] !== null) {
                    $limit = $limitRes['limit'];
                }
            }
        }

        // NB : la validation métier stricte du code d'accès peut être ajoutée ici si besoin
        // (ex. vérifier l'existence d'au moins un tarif avec ce code pour l'event).

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors, 'data' => []];
        }

        // 4) Persistance en session (structure simple pour JS)
        $this->reservationSessionService->setReservationSession('event_id', $dto->eventId);
        $this->reservationSessionService->setReservationSession('event_session_id', $dto->eventSessionId);
        $this->reservationSessionService->setReservationSession('swimmer_id', $dto->swimmerId);
        $this->reservationSessionService->setReservationSession('access_code_used', $accessCode);
        $this->reservationSessionService->setReservationSession('limitPerSwimmer', $limit);

        // Payload lisible par JS
        $data = [
            'eventId' => $dto->eventId,
            'eventSessionId' => $dto->eventSessionId,
            'swimmerId' => $dto->swimmerId,
            'accessCode' => $accessCode,
            'limitPerSwimmer' => $limit,
        ];

        return ['success' => true, 'errors' => [], 'data' => $data];
    }
}
