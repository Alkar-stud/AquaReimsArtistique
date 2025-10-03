<?php
// php
namespace app\Services\DataValidation;

use app\DTO\ReservationDetailItemDTO;
use app\DTO\ReservationSelectionSessionDTO;
use app\DTO\ReservationUserDTO;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventTarifRepository;
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
    private EventTarifRepository $eventTarifRepository;

    public function __construct(
        EventRepository                  $eventRepository,
        SwimmerRepository                $swimmerRepository,
        SwimmerQueryService              $swimmerQueryService,
        ReservationSessionService        $reservationSessionService,
        EventQueryService                $eventQueryService,
        EventTarifRepository             $eventTarifRepository,
    ) {
        $this->eventRepository = $eventRepository;
        $this->swimmerRepository = $swimmerRepository;
        $this->swimmerQueryService = $swimmerQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->eventQueryService = $eventQueryService;
        $this->eventTarifRepository = $eventTarifRepository;
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
        $defaults = $this->reservationSessionService->getDefaultReservationStructure();
        $session = $this->reservationSessionService->getReservationSession() ?? [];

        if ($step >= 1) {
            $effective = array_replace_recursive($defaults, $session, $data);

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
            $effective = array_replace_recursive($defaults, $session);
            // On fusionne les données de $data dans la clé 'booker'
            $effective['booker'] = array_replace($effective['booker'], $data);

            $dto = ReservationUserDTO::fromArray($effective);

            $check = $this->validateStep2($dto);
            if ($check['success'] === false) {
                return ['success' => false, 'errors' => $check['errors']];
            }

            if ($step === 2) {
                $this->persistStep2($dto);
                return ['success' => true, 'errors' => [], 'data' => []];
            }
        }

        if ($step >= 3) {
            $effective = array_replace_recursive($defaults, $session);
            //On récupère tous les tarifs de l'event
            $allEventTarifs = $this->eventTarifRepository->findSeatedTarifsByEvent($effective['event_id']);

            //Boucle sur $data['tarif']
            $items = [];
            foreach ($data['tarifs'] as $tarif_id => $qty) {
                //On boucle sur la quantité
                for ($i = 0; $i < $qty; $i++) {
                    //On vérifie s'il y a code spécial demandé que ce soit bien le bon qui est fourni
                    $codeRequis = $allEventTarifs[$tarif_id]->getAccessCode();

                    if ($codeRequis !== null) {
                        if (isset($data['special']) && is_array($data['special']) && array_key_exists($tarif_id, $data['special'])) {
                            if ($data['special'][$tarif_id] !== $codeRequis) {
                                return ['success' => false, 'error' => 'Code d\'accès invalide pour le tarif sélectionné.'];
                            }
                        }
                    }
                    //Puis sur le nombre de places dans le tarif (pour les packs multi-places)
                    $nbPlacesInPack = $allEventTarifs[$tarif_id]->getSeatCount();
                    for ($j = 0; $j < $nbPlacesInPack; $j++) {
                        //On génère le dto qu'on ajoute
                        $items[] = ReservationDetailItemDTO::fromArrayWithSpecialPrice($tarif_id, $data, $codeRequis);
                    }
                }
            }

            // On rejette si vide
            if (empty($items)) {
                return ['success' => false, 'errors' => ['tarifs' => 'Aucun tarif sélectionné.'], 'data' => []];
            }

            $eventIdPayload = (int)($data['event_id'] ?? 0);
            $eventIdSession = (int)($session['event_id'] ?? 0);
            if ($eventIdPayload <= 0 || $eventIdPayload !== $eventIdSession) {
                return ['success' => false, 'errors' => ['event_id' => 'Événement incohérent.'], 'data' => []];
            }

            if ($step === 3) {
                $this->persistStep3($items);
                return ['success' => true, 'errors' => [], 'data' => []];
            }


            return ['success' => true, 'errors' => [], 'data' => []];
        }

        return ['success' => false, 'errors' => ['message' => 'ne correspond pas'], 'data' => []];
    }

    /**
     * Pour valider les étapes précédentes
     *
     * @param int $step
     * @param array $session
     * @return bool
     */
    public function validatePreviousStep(int $step, array $session): bool
    {
        // Fusionne la structure par défaut et la session pour éviter les clés manquantes
        $defaults = $this->reservationSessionService->getDefaultReservationStructure();
        $effective = array_replace_recursive($defaults, $session);

        //On valide l'étape 1, on return si false
        if ($step >= 1) {
            $dto1 = ReservationSelectionSessionDTO::fromArray($effective);
            if (!$this->validateStep1($dto1)['success']) {
                return false;
            }
        }

        if ($step >= 2) {
            $dto2 = ReservationUserDTO::fromArray($effective);
            if (!$this->validateStep2($dto2)['success']) {
                return false;
            }
        }

        if ($step >= 3) {
            $dto3 = ReservationDetailItemDTO::fromArray($effective);
            if ($this->validateStep3($dto3)['success']) {
echo $step;
                print_r($this->validateStep3($dto3)['success']);
                die;
                return false;
            }
        }

        // Étapes suivantes à ajouter ici
        return true;
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
                    $check = $this->eventQueryService->validateAccessCode($event->getId(), htmlspecialchars((string)$dto->accessCode));
                    if (!($check['success'] ?? false)) {
                        $errors['accessCode'] = $check['error'] ?? 'Code d\'accès invalide.';
                    } else {
                        // Mémoriser pour les étapes suivantes
                        $this->reservationSessionService->setReservationSession('access_code_used', $dto->accessCode);
                    }
                }
            }
        }

        // 4) Nageur (si limitation active)
        $limit = $event?->getLimitationPerSwimmer();
        $accessCode = $dto->accessCode ? trim($dto->accessCode) : null;

        if ($limit !== null && $dto->swimmerId === null && $accessCode === null) {
            $errors['swimmerOrAccessCode'] = 'Le nom d\'une nageuse est requis pour cet événement.';
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

    /**
     * Étape 2: valide [booker]=< name, firstname, email, phone
     * Retourne ['success'=>bool, 'errors'=>array]
     *
     * @param ReservationUserDTO $dto
     * @return array
     */
    public function validateStep2(ReservationUserDTO $dto): array
    {
        $errors = [];

        //sanitize les données
        $dto->name = htmlspecialchars(trim($dto->name), true, 'UTF-8');
        mb_strtoupper($dto->name, 'UTF-8');
        $dto->firstname = htmlspecialchars(trim($dto->firstname), true, 'UTF-8');
        mb_convert_case(mb_strtolower($dto->firstname, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        $dto->email = trim($dto->email);
        if (!filter_var($dto->email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email invalide.';
        }
        if (!empty($dto->phone) && !preg_match('/^(?:0[1-9]\d{8}|\+33[1-9]\d{8})$/', str_replace(' ', '', $dto->phone))) {
            $errors['phone'] = 'Numéro de téléphone invalide (doit être au format 0XXXXXXXXX ou +33XXXXXXXXX).';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        return ['success' => true, 'errors' => []];
    }

    /**
     * Persiste suite validation step2
     * @param ReservationUserDTO $dto
     * @return void
     */
    public function persistStep2(ReservationUserDTO $dto): void
    {
        // Persistance en session (structure simple pour JS et $_SESSION)
        $this->reservationSessionService->setReservationSession(['booker', 'name'], $dto->name);
        $this->reservationSessionService->setReservationSession(['booker', 'firstname'], $dto->firstname);
        $this->reservationSessionService->setReservationSession(['booker', 'email'], $dto->email);
        $this->reservationSessionService->setReservationSession(['booker', 'phone'], $dto->phone);
    }


    /**
     * Persiste suite validation step3
     * @param ReservationDetailItemDTO[] $dtos
     * @return void
     */
    public function persistStep3(array $dtos): void
    {
        // Persistance en session (structure simple pour JS et $_SESSION)

        $this->reservationSessionService->setReservationSession('reservation_detail', array_map(static fn(ReservationDetailItemDTO $i) => $i->jsonSerialize(), $dtos));
    }

}
