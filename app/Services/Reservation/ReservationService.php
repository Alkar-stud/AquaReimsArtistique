<?php

namespace app\Services\Reservation;

use app\Models\Reservation\ReservationMailsSent;
use app\Repository\Event\EventsRepository;
use app\Repository\MailTemplateRepository;
use app\Repository\Reservation\ReservationMailsSentRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\TarifsRepository;
use app\Services\Logs\LogService;
use app\Services\Mails\MailPrepareService;
use app\Services\SessionValidationService;
use DateMalformedStringException;
use Exception;


class ReservationService
{
    private EventsRepository $eventsRepository;
    private ReservationsRepository $reservationsRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private TarifsRepository $tarifsRepository;
    private MailTemplateRepository $mailTemplateRepository;
    private ReservationMailsSentRepository $reservationMailsSentRepository;
    private MailPrepareService $mailPrepareService;
    private SessionValidationService $sessionValidationService;
    private LogService $logService;
    private ReservationSessionService $reservationSessionService;
    private ReservationValidationService $reservationValidationService;
    private ReservationCartService $reservationCartService;


    public function __construct()
    {
        $this->eventsRepository = new EventsRepository();
        $this->reservationsRepository = new ReservationsRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->tarifsRepository = new TarifsRepository();
        $this->mailTemplateRepository = new MailTemplateRepository();
        $this->reservationMailsSentRepository = new ReservationMailsSentRepository();
        $this->mailPrepareService = new MailPrepareService();
        $this->sessionValidationService = new SessionValidationService();
        $this->logService = new LogService();
        $this->reservationSessionService = new ReservationSessionService();
        $this->reservationValidationService = new ReservationValidationService();
        $this->reservationCartService = new ReservationCartService();
    }

    /**
     * Valide le contexte de base de la réservation (événement, séance, nageuse).
     * C'est le prérequis pour l'étape 2.
     *
     * @param array|null $reservationData
     * @return array
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function validateCoreContext(?array $reservationData): array
    {
        if (!$this->sessionValidationService->isSessionActive($reservationData, 'last_activity', TIMEOUT_PLACE_RESERV)) {
            return ['success' => false, 'error' => 'session_expiree'];
        }

        if (empty($reservationData['event_id']) || empty($reservationData['event_session_id'])) {
            return ['success' => false, 'error' => 'donnees_manquantes'];
        }

        $event = $this->eventsRepository->findById($reservationData['event_id']);
        if (!$event) {
            return ['success' => false, 'error' => 'evenement_invalide'];
        }

        if ($event->getLimitationPerSwimmer() !== null && empty($reservationData['nageuse_id'])) {
            return ['success' => false, 'error' => 'nageuse_manquante'];
        }

        return ['success' => true, 'error' => null];
    }


    /**
     * Renvoie les emails de confirmation pour un événement et un email donnés.
     *
     * @param int $eventId
     * @param string $email
     * @return array
     * @throws Exception
     */
    public function resendConfirmationEmails(int $eventId, string $email): array
    {
        if (empty($email) || empty($eventId)) {
            return ['success' => false, 'error' => 'Paramètres manquants.'];
        }

        $reservations = $this->reservationsRepository->findByEmailAndEvent($email, $eventId);
        if (empty($reservations)) {
            return ['success' => false, 'error' => 'Aucune réservation trouvée pour cet email et cet événement.'];
        }

        //On récupère le contenu du mail à envoyer
        $template = $this->mailTemplateRepository->findByCode('paiement_confirme');
        if (!$template) {
            return ['success' => false, 'error' => 'Template de mail introuvable.'];
        }

        $sentCount = 0;
        $limitReachedCount = 0;

        foreach ($reservations as $reservation) {
            // Vérifier la limite d'envoi
            $confirmationSentCount = $this->reservationMailsSentRepository->countSentMails($reservation->getId(), $template->getId());

            if ($confirmationSentCount >= 2) { // Original + 1 renvoi
                $limitReachedCount++;
                continue; // Limite atteinte, on passe au suivant
            }

            // Hydrater l'objet event pour le service mail
            $event = $this->eventsRepository->findById($reservation->getEvent());
            $reservation->setEventObject($event);

            if ($this->mailPrepareService->sendReservationConfirmationEmail($reservation)) {
                $mailSentRecord = new ReservationMailsSent();
                $mailSentRecord->setReservation($reservation->getId())->setMailTemplate($template->getId())->setSentAt(date('Y-m-d H:i:s'));
                $this->reservationMailsSentRepository->insert($mailSentRecord);
                $sentCount++;
            }
        }

        if ($sentCount === 0 && $limitReachedCount > 0) {
            return ['success' => false, 'error' => "Aucun mail n'a pu être renvoyé car la limite de renvoi a déjà été atteinte pour toutes les réservations concernées. Veuillez vous rapprocher des organisateurs : " . EMAIL_GALA];
        }
        return ['success' => true, 'message' => "$sentCount mail(s) de confirmation renvoyé(s). $limitReachedCount réservation(s) avaient déjà atteint la limite de renvoi."];
    }


    /**
     * Vérifie si des réservations existent pour un email et un événement, et agrège les informations.
     *
     * @param int $eventId
     * @param string $email
     * @return array
     * @throws DateMalformedStringException
     */
    public function checkExistingReservations(int $eventId, string $email): array
    {
        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            return ['exists' => false, 'error' => 'Événement invalide.'];
        }

        $reservations = $this->reservationsRepository->findByEmailAndEvent($email, $eventId);
        if (empty($reservations)) {
            return ['exists' => false];
        }

        $tarifs = $this->tarifsRepository->findByEventId($eventId);
        $totalPlacesReserved = 0;
        $reservationSummaries = [];

        foreach ($reservations as $r) {
            $sessionObj = null;
            foreach ($event->getSessions() as $s) {
                if ($s->getId() == $r->getEventSession()) {
                    $sessionObj = $s;
                    break;
                }
            }

            $details = $this->reservationsDetailsRepository->findByReservation($r->getId());
            $nbPlacesForThisReservation = $this->reservationCartService->countSeatedPlaces($details, $tarifs);
            $totalPlacesReserved += $nbPlacesForThisReservation;

            $reservationSummaries[] = [
                'nb_places' => $nbPlacesForThisReservation,
                'session_date' => $sessionObj ? $sessionObj->getEventStartAt()->format('d/m/Y H:i') : 'N/A'
            ];
        }

        return [
            'exists' => true,
            'total_places_reserved' => $totalPlacesReserved,
            'num_reservations' => count($reservations),
            'reservation_summaries' => $reservationSummaries
        ];
    }

    /**
     * Valide que les informations du payeur sont présentes et correctes.
     * C'est le prérequis pour l'étape 3.
     *
     * @param array|null $reservationData
     * @return array
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function validatePayerContext(?array $reservationData): array
    {
        $coreContextValidation = $this->validateCoreContext($reservationData);
        if (!$coreContextValidation['success']) {
            return $coreContextValidation;
        }

        if (empty($reservationData['user'])) {
            return ['success' => false, 'error' => 'donnees_payeur_manquantes'];
        }

        // On pourrait aussi re-valider le format des données de l'utilisateur ici si on voulait être très strict.

        return ['success' => true, 'error' => null];
    }

    /**
     * Valide que les détails de la réservation (tarifs) sont présents.
     * C'est le prérequis pour l'étape 4.
     *
     * @param array|null $reservationData
     * @return array
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function validateDetailsContextStep4(?array $reservationData): array
    {
        try {
            // Revalide le contexte de base lié à l'événement choisi (prérequis pour l'étape 2)
            $coreContextValidation = $this->validateCoreContext($reservationData);
            if (!$coreContextValidation['success']) {
                return $coreContextValidation;
            }

            $payerContextValidation = $this->validatePayerContext($reservationData);
            if (!$payerContextValidation['success']) {
                return $payerContextValidation;
            }

        } catch (DateMalformedStringException $e) {
            // On enregistre l'erreur technique pour le débogage.
            $this->logService->logAndReturnError("Erreur de format de date lors de la validation du contexte: " . $e->getMessage());
            // On retourne une erreur générique et propre à l'utilisateur.
            return ['success' => false, 'error' => 'Une erreur interne est survenue (format de date invalide).'];
        }

        if (empty($reservationData['reservation_detail'])) {
            return ['success' => false, 'error' => 'details_reservation_manquants'];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * Validation étape par étape. L'étape courante avec le tableau $input et les précédentes avec le contenu de $_SESSION
     *
     * @param int $step
     * @param array $dataInputed
     * @return array
     * @throws DateMalformedStringException
     */
    public function validateDataPerStep(int $step, array $dataInputed): array
    {
        $reservationDataSession = $this->reservationSessionService->getReservationSession();

        if ($step >= 1) {
            $step == 1 ? $data = $dataInputed : $data = $reservationDataSession;
            $return = $this->reservationValidationService->verifyPrerequisitesStep1($data['event_id'], $data['event_session_id'], $data['nageuse_id']);

            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 2) {
            if ($step == 2) {
                $data = $dataInputed;
            } else {
                $data = $reservationDataSession['user'];
            }
            $return = $this->reservationValidationService->validatePayerInformationStep2($data);

            // Si la validation de l'étape 2 réussit, on la considère comme la donnée de retour principale.
            // Sinon, on retourne l'erreur.
            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 3) {
            if ($step == 3) {
                // Pour la soumission de l'étape 3, on utilise les données du formulaire.
                $dataForStep3 = $dataInputed;
            } else {
                // Pour les étapes suivantes, on reconstruit un input de type "étape 3"
                // à partir des données déjà validées et stockées en session.
                $allEventTarifs = $this->tarifsRepository->findByEventId($reservationDataSession['event_id']);
                $tarifQuantities = $this->reservationCartService->getTarifQuantitiesFromDetails($reservationDataSession['reservation_detail'] ?? [], $allEventTarifs);

                $reconstructedTarifs = [];
                foreach ($tarifQuantities as $tarifId => $qty) {
                    $reconstructedTarifs[] = ['id' => $tarifId, 'qty' => $qty];
                }
                // On ne reconstruit pas le 'code' ici, car la validation des codes spéciaux est déjà implicite dans les `reservation_detail` en session.
                $dataForStep3 = ['tarifs' => $reconstructedTarifs];
            }

            $return = $this->reservationValidationService->processAndValidateStep3($dataForStep3, $reservationDataSession);

            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 4) {
            if ($step == 4) {
                // Pour la soumission de l'étape 4, on utilise les données du formulaire.
                $postData = $dataInputed[0] ?? [];
                $filesData = $dataInputed[1] ?? [];
                $return = $this->reservationValidationService->processAndValidateStep4($postData, $filesData, $reservationDataSession);
            } else {
                // Pour les étapes suivantes, on reconstruit les noms/prénoms à partir de la session.
                $details = $reservationDataSession['reservation_detail'] ?? [];
                $postData = [
                    'noms' => array_column($details, 'nom'),
                    'prenoms' => array_column($details, 'prenom')
                ];
                // On passe un tableau de fichiers vide, car on ne re-valide pas les uploads.
                $return = $this->reservationValidationService->processAndValidateStep4($postData, [], $reservationDataSession);
            }

            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 5) {
            // On vérifie si l'événement a des places numérotées avant de valider l'étape 5.
            $event = $this->eventsRepository->findById($reservationDataSession['event_id']);
            $shouldSkipStep5 = false;
            if ($event) {
                //Si pas de places numérotées dans la piscine de l'event, on saute la vérification 5 qui correspond au choix des places sur le plan.
                if (!$event->getPiscine()->getNumberedSeats()) {
                    $shouldSkipStep5 = true;
                }
            }

            // Si l'étape 5 doit être sautée, on ne la valide pas.
            if (!$shouldSkipStep5) {
                if ($step == 5) {
                    // Pour la soumission de l'étape 5, on utilise les données du formulaire.
                    $dataForStep5 = $dataInputed;
                } else {
                    // Pour les étapes suivantes, on reconstruit la liste des places à partir de la session.
                    $details = $reservationDataSession['reservation_detail'] ?? [];
                    $seatIds = array_filter(array_column($details, 'seat_id'));
                    $dataForStep5 = ['seats' => $seatIds];
                }

                $return = $this->reservationValidationService->processAndValidateStep5($dataForStep5, $reservationDataSession);

                if (!$return['success']) {
                    return $return;
                }
            }
        }

        if ($step >= 6) {
            // Pour l'étape 6, la re-validation n'est pas nécessaire, car elle n'a pas d'impact
            // sur les étapes précédentes et ne modifie pas les 'reservation_detail'.
            // On ne la valide que lors de sa soumission directe.
            if ($step == 6) {
                $return = $this->reservationValidationService->processAndValidateStep6($dataInputed, $reservationDataSession);
            } else {
                // Si on valide une étape > 6 (inexistante pour l'instant), on considère la 6 comme valide.
                $return = ['success' => true, 'data' => $reservationDataSession['reservation_complement'] ?? []];
            }

            if (!$return['success']) {
                return $return;
            }
        }

        // Cas pour l'étape 1 ou si aucune étape supérieure n'est validée
        return ['success' => true, 'error' => null, 'data' => $return['data']];
    }

    /**
     * Applique les règles de mise en forme (casse) pour les champs 'nom' et 'prenom'.
     *
     * @param string|null $field Le nom du champ.
     * @param string|null $value La valeur à formater.
     * @return string|null La valeur formatée.
     */
    public function normalizeFieldValue(?string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($field === 'nom') {
            return mb_strtoupper($value, 'UTF-8');
        }

        if ($field === 'prenom') {
            // Met en majuscule la première lettre de chaque mot (gère les prénoms composés)
            return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
        }

        return $value;
    }


}