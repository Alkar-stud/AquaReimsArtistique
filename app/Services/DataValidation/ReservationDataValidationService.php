<?php
// php
namespace app\Services\DataValidation;

use app\DTO\ReservationSelectionSessionDTO;
use app\Repository\Event\EventRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Services\Event\EventQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;

class ReservationDataValidationService
{
    private EventRepository $eventRepository;
    private SwimmerRepository $swimmerRepository;
    private SwimmerQueryService $swimmerQueryService;
    private ReservationSessionService $reservationSessionService;
    private EventQueryService $eventQueryService;

    public function __construct(
        EventRepository           $eventRepository,
        SwimmerRepository         $swimmerRepository,
        SwimmerQueryService       $swimmerQueryService,
        ReservationSessionService $reservationSessionService,
        EventQueryService           $eventQueryService,
    ) {
        $this->eventRepository = $eventRepository;
        $this->swimmerRepository = $swimmerRepository;
        $this->swimmerQueryService = $swimmerQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->eventQueryService = $eventQueryService;
    }

    /**
     * Validation étape par étape. L'étape courante avec le tableau $input et les précédentes avec le contenu de $_SESSION
     *
     * @param int $step
     * @param array $data
     * @return array
     */
    public function validateAndPersistDataPerStep(int $step, array $data): array
    {
        $data ??= [];
        //On récupère la session, à l'étape 1 elle doit être vide, mais existe.
        $session = $this->reservationSessionService->getReservationSession();
        if (!$session) {
            return ['success' => false, 'errors' => [], 'data' => []];
        }

        // Valeurs par défaut issues de la session pour combler un payload incomplet
        $defaults = [
            'event_id'          => $session['event_id']          ?? null,
            'event_session_id'  => $session['event_session_id']  ?? null,
            'swimmer_id'        => $session['swimmer_id']        ?? null,
            'limit_per_swimmer'   => $session['limit_per_swimmer']   ?? null,
            // Normalisation: on alimente `accessCode` depuis la session.
            'access_code_used'        => $session['access_code_used']  ?? null,
        ];

        // `$data` prime, `+` conserve les clés manquantes depuis `$defaults`
        $effective = $data + array_filter($defaults, static fn($v) => $v !== null);

        if ($step >= 1) {
            $dto = ReservationSelectionSessionDTO::fromArray($effective);

            $check = $this->validateStep1($dto);
            if ($check['success'] === false) {
                return ['success' => false, 'errors' => $check['errors']];
            }

            if ($step === 1) {
                $this->persistStep1($dto);
                return ['success' => true, 'errors' => [], 'data' => []];
            }
        }

        if ($step >= 2) {
            //On vérifie si la session n'est pas expirée


        }


        return ['success' => true, 'errors' => [], 'data' => []];
    }


    /**
     * Étape 1: valide event, session, nageur ou code d'accès.
     * Retourne ['success'=>bool, 'errors'=>array]
     *
     * @param ReservationSelectionSessionDTO $dto
     * @return array
     */
    public function validateStep1(ReservationSelectionSessionDTO $dto): array
    {
        $errors = [];

        // 1) Event
        $event = $this->eventRepository->findById($dto->eventId, false, true, true);
        if (!$event) {
            $errors['eventId'] = 'Événement introuvable.';
        }

        // 2) Session
        $session = null;
        if ($dto->eventSessionId <= 0) {
            $errors['eventSessionId'] = 'Session manquante.';
        } elseif ($event) {
            $sessions = method_exists($event, 'getSessions') ? ($event->getSessions() ?? []) : [];
            foreach ($sessions as $s) {
                // On matche par ID pour éviter un nouvel accès repository
                if ($s->getId() === $dto->eventSessionId) {
                    $session = $s;
                    break;
                }
            }
            if (!$session) {
                $errors['eventSessionId'] = 'Session introuvable pour cet événement.';
            }
        }

        // 3) Vérification du code si besoin pour la période d'inscription
        if ($event && empty($errors['eventId'])) {
            //On vérifie si la période d'inscription nécessite un code
            $periodsStatus = $this->eventQueryService->getEventInscriptionPeriodsStatus([$event]);
            $activePeriod = $periodsStatus['periodesOuvertes'][$event->getId()] ?? null;

            //Si période active avec un code access
            if ($activePeriod && $activePeriod->getAccessCode() !== null) {
                if ($dto->accessCode === '') {
                    $errors['accessCode'] = 'Un code d\'accès est requis pour la période d\'inscription en cours.';
                } else {
                    //On vérifie si le code saisi est valide
                    $check = $this->eventQueryService->validateAccessCode($event->getId(), $dto->accessCode);
                    if (!($check['success'] ?? false)) {
                        $errors['accessCode'] = $check['error'] ?? 'Code d\'accès invalide.';
                    } else {
                        // Mémoriser pour les étapes suivantes
                        $this->reservationSessionService->setReservationSession('access_code_used', $dto->accessCode);
                    }
                }
            }
        }

        // 4) Nageur ou code d'accès (si limitation active)
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
                //On enregistre la limite dans le DTO
                $dto->limitPerSwimmer = $limitRes['limit'];
                if ($limitRes['error']) {
                    $errors['swimmerId'] = $limitRes['error'];
                } elseif (!empty($limitRes['limitReached'])) {
                    $errors['swimmerId'] = 'La limite de spectateurs pour cette nageuse est atteinte.';
                }
            }
        }
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        return ['success' => true, 'errors' => []];
    }

    /**
     * Persiste suite validation step1
     * @param ReservationSelectionSessionDTO $dto
     * @return void
     */
    public function persistStep1(ReservationSelectionSessionDTO $dto): void
    {
        // Persistance en session (structure simple pour JS)
        $this->reservationSessionService->setReservationSession('event_id', $dto->eventId);
        $this->reservationSessionService->setReservationSession('event_session_id', $dto->eventSessionId);
        $this->reservationSessionService->setReservationSession('swimmer_id', $dto->swimmerId);
        $this->reservationSessionService->setReservationSession('access_code_used', $dto->accessCode);
        $this->reservationSessionService->setReservationSession('limit_per_swimmer', $dto->limitPerSwimmer);
    }

}
