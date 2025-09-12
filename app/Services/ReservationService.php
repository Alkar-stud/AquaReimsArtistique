<?php

namespace app\Services;

use app\DTO\ReservationDetailItemDTO;
use app\Models\Reservation\ReservationMailsSent;
use app\DTO\ReservationUserDTO;
use app\Models\Reservation\ReservationsDetails;
use app\Repository\MailTemplateRepository;
use app\Repository\Event\EventsRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationMailsSentRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\TarifsRepository;
use DateMalformedStringException;
use DateTime;
use Exception;

class ReservationService
{
    private EventsRepository $eventsRepository;
    private NageuseService $nageuseService;
    private ReservationsRepository $reservationsRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private TarifsRepository $tarifsRepository;
    private MailTemplateRepository $mailTemplateRepository;
    private ReservationMailsSentRepository $reservationMailsSentRepository;
    private NageusesRepository $nageusesRepository;
    private MailService $mailService;
    private TarifService $tarifService;
    private SessionValidationService $sessionValidationService;
    private LogService $logService;
    private ReservationSessionService $reservationSessionService;
    private UploadService $uploadService;
    private PiscineGradinsPlacesRepository $placesRepository;
    private ReservationsPlacesTempRepository $tempRepo;
    private PiscineGradinsZonesRepository $zonesRepository;


    public function __construct()
    {
        $this->eventsRepository = new EventsRepository();
        $this->nageuseService = new NageuseService();
        $this->reservationsRepository = new ReservationsRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->tarifsRepository = new TarifsRepository();
        $this->mailTemplateRepository = new MailTemplateRepository();
        $this->nageusesRepository = new NageusesRepository();
        $this->reservationMailsSentRepository = new ReservationMailsSentRepository();
        $this->mailService = new MailService();
        $this->tarifService = new TarifService();
        $this->sessionValidationService = new SessionValidationService();
        $this->logService = new LogService();
        $this->reservationSessionService = new ReservationSessionService();
        $this->uploadService = new UploadService();
        $this->placesRepository = new PiscineGradinsPlacesRepository();
        $this->tempRepo = new ReservationsPlacesTempRepository();
        $this->zonesRepository = new PiscineGradinsZonesRepository();
    }

    /**
     * Vérifie les prérequis d'une réservation (événement, séance, quota nageuse).
     *
     * @param int $eventId
     * @param int $sessionId
     * @param int|null $nageuseId
     * @return array ['success' => bool, 'error' => ?string]
     * @throws DateMalformedStringException
     */
    public function verifyPrerequisitesStep1(int $eventId, int $sessionId, ?int $nageuseId): array
    {
        // On vérifie si l'événement et la session existent avec date de fin d'inscription à venir
        $resultCheckEvent = $this->checkEventExistAndIsUpComing($eventId, $sessionId);
        if (!$resultCheckEvent['success']) {
            return $resultCheckEvent;
        }
        $event = $resultCheckEvent['event'];

        // On vérifie si une limitation par nageuse est active et si elle n'est pas atteinte
        $resultCheckNageuseLimitation = $this->checkNageuseLimitation($event->getLimitationPerSwimmer(),$eventId,$nageuseId);
        if (!$resultCheckNageuseLimitation['success']) {
            return $resultCheckNageuseLimitation;
        }

        return ['success' => true, 'error' => null, 'data' => null];
    }

    /**
     * Vérifie si l'événement et la session existent avec date de fin d'inscription à venir
     *
     * @param $eventId
     * @param $sessionId
     * @return array
     * @throws DateMalformedStringException
     */
    public function checkEventExistAndIsUpComing($eventId, $sessionId): array
    {
        $event = $this->eventsRepository->findById($eventId);
        //Si l'event existe
        if (!$event) {
            return $this->logService->logAndReturnError('Événement invalide.', ['event_id' => $eventId], ['event' => null]);
        }
        //Si la session appartient bien à l'event
        $sessionIds = array_map(fn($s) => $s->getId(), $event->getSessions());
        if (!in_array($sessionId, $sessionIds, true)) {
            return $this->logService->logAndReturnError('Séance invalide', ['event_id' => $eventId, 'event_session_id' => $sessionId], ['event' => null]);
        }

        // Vérifier si la période d'inscription est active et si le bon code a été saisi (il peut ne pas y en avoir)
        $now = new DateTime();
        $accessCodeInSession = $this->reservationSessionService->getReservationSession()['access_code_used']['code'] ?? null;
        $isWithinDateRange = false;
        $inscriptionPeriods = $event->getInscriptionDates();
        //On liste les périodes d'inscription de l'event
        foreach ($inscriptionPeriods as $inscriptionDate) {
            //Si la date courante correspond à une période d'inscription valide
            if ($inscriptionDate->getStartRegistrationAt() <= $now && $inscriptionDate->getCloseRegistrationAt() >= $now) {
                //Si la période ne demande pas de code, c'est bon.
                //Si la période demande un code, on vérifie si le même code a été saisi
                if  ($inscriptionDate->getAccessCode() === null) {
                    $isWithinDateRange = true;
                    break; // Période valide trouvée
                } elseif ($inscriptionDate->getAccessCode() === $accessCodeInSession) {
                    $isWithinDateRange = true;
                    break; // Période valide trouvée
                }
            }
        }

        if (!$isWithinDateRange) {
            return $this->logService->logAndReturnError("La période d'inscription pour cette séance est terminée ou n'a pas encore commencé ou le code saisi n'est pas le bon.", ['event_id' => $eventId, 'event_session_id' => $sessionId, 'access_code_used' => $accessCodeInSession]);
        }

        return ['success' => true, 'event' => $event];
    }

    /**
     * Vérifie si une limitation par nageuse est active et si elle n'est pas atteinte
     *
     * @param $limitationPerSwimmer
     * @param $eventId
     * @param $nageuseId
     * @return array
     * @throws DateMalformedStringException
     */
    public  function checkNageuseLimitation($limitationPerSwimmer,$eventId,$nageuseId): array
    {
        if ($limitationPerSwimmer !== null) {
            if ($nageuseId === null) {
                return ['success' => false, 'error' => 'La sélection d\'une nageuse est obligatoire pour cet événement.'];
            }

            $limitCheck = $this->nageuseService->isSwimmerLimitReached($eventId, $nageuseId);
            if ($limitCheck['error']) {
                return ['success' => false, 'error' => $limitCheck['error']];
            }
            if ($limitCheck['limitReached']) {
                return ['success' => false, 'error' => 'Le quota de spectateurs pour cette nageuse est atteint.'];
            }
        }
        return ['success' => true, 'error' => null];
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

            if ($this->mailService->sendReservationConfirmationEmail($reservation)) {
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
     * Compte le nombre de places assises (numérotées) dans un ensemble de détails de réservation.
     *
     * @param array $reservationDetails Les détails de la réservation (peut contenir des objets ou des tableaux).
     * @param array $tarifs La liste complète des tarifs de l'événement pour référence.
     * @return int Le nombre de places assises.
     */
    public function countSeatedPlaces(array $reservationDetails, array $tarifs): int
    {
        $nb = 0;
        // Création d'une carte pour une recherche rapide des tarifs par ID
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        foreach ($reservationDetails as $detail) {
            $tarifId = is_array($detail) ? ($detail['tarif_id'] ?? null) : $detail->getTarif();
            if ($tarifId !== null) {
                $tarif = $tarifsById[$tarifId] ?? null;
                // Une place est considérée comme "assise" si son tarif possède un nombre de places défini (getNbPlace n'est pas null).
                if ($tarif && $tarif->getNbPlace() !== null) {
                    $nb++;
                }
            }
        }
        return $nb;
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
            $nbPlacesForThisReservation = $this->countSeatedPlaces($details, $tarifs);
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
     * Valide les informations de l'acheteur (nom, prénom, email, téléphone).
     *
     * @param array $input
     * @return array ['success' => bool, 'error' => ?string, 'data' => ?ReservationUserDTO]
     */
    public function validatePayerInformationStep2(array $input): array
    {
        // Nettoyage initial pour enlever les balises HTML/PHP
        $nom = strip_tags(trim($input['nom'] ?? ''));
        $prenom = strip_tags(trim($input['prenom'] ?? ''));
        $email = strip_tags(trim($input['email'] ?? ''));
        $telephone = strip_tags(trim($input['telephone'] ?? ''));


        if (empty($nom) || empty($prenom) || empty($email)) {
            return ['success' => false, 'error' => 'Tous les champs sont obligatoires.'];
        }

        if (strtolower($nom) === strtolower($prenom)) {
            return ['success' => false, 'error' => 'Le nom et le prénom ne doivent pas être identiques.'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Adresse mail invalide.'];
        }
        if (!empty($telephone) && !preg_match('/^0[1-9](\d{8})$/', str_replace(' ', '', $telephone))) {
            return ['success' => false, 'error' => 'Numéro de téléphone invalide.'];
        }

        return [
            'success' => true,
            'error' => null,
            'data' => new ReservationUserDTO(
                nom: strtoupper($nom),
                prenom: ucwords(strtolower($prenom)), // ucwords fonctionne mieux sur du texte en minuscules
                email: $email,
                telephone: $telephone
            )
        ];
    }

  /**
     * Calcule les quantités pour chaque tarif à partir des détails de la réservation.
     *
     * @param array $reservationDetails Les détails de la réservation (liste des participants).
     * @param array $allEventTarifs La liste complète des objets Tarif de l'événement.
     * @return array Un tableau associatif [tarif_id => quantity].
     */
    public function getTarifQuantitiesFromDetails(array $reservationDetails, array $allEventTarifs): array
    {
        if (empty($reservationDetails)) {
            return [];
        }
        // Compter le nombre de places pour chaque tarif_id
        $tarifIds = array_column($reservationDetails, 'tarif_id');
        $placesPerTarifId = array_count_values($tarifIds);

        // Convertir le nombre de places en nombre de "packs" (quantité de tarifs)
        $tarifQuantities = [];
        foreach ($allEventTarifs as $tarif) {
            $tarifId = $tarif->getId();
            $nbPlacesInTarif = $tarif->getNbPlace() ?? 1;
            if (isset($placesPerTarifId[$tarifId])) {
                // On s'assure que la division est entière et logique.
                // Si on a 4 places pour un tarif de 4 places, ça fait 1 pack.
                // La division par zéro est évitée car getNbPlace() renvoie 1 par défaut.
                $tarifQuantities[$tarifId] = (int)($placesPerTarifId[$tarifId] / $nbPlacesInTarif);
            }
        }
        return $tarifQuantities;
    }

    /**
     * Prépare un "ViewModel" complet de l'état actuel de la réservation pour affichage.
     *
     * @param array $reservationData Les données de la session de réservation.
     * @return array|null Un tableau contenant les données pour la vue, ou null si l'événement est invalide.
     * @throws DateMalformedStringException
     */
    public function getReservationViewModel(array $reservationData): ?array
    {
        $eventId = $reservationData['event_id'] ?? null;
        if (!$eventId) {
            return null;
        }

        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            return null;
        }

        // Get session object
        $session = null;
        $sessionId = $reservationData['event_session_id'] ?? null;
        if ($sessionId) {
            foreach ($event->getSessions() as $s) {
                if ($s->getId() == $sessionId) {
                    $session = $s;
                    break;
                }
            }
        }

        // Get nageuse object
        $nageuse = null;
        $nageuseId = $reservationData['nageuse_id'] ?? null;
        if ($nageuseId) {
            $nageuse = $this->nageusesRepository->findById($nageuseId);
        }

        $tarifs = $this->tarifsRepository->findByEventId($eventId);
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif->getLibelle();
        }

        $reservationDetails = $reservationData['reservation_detail'] ?? [];

        $status = ['reserved' => null, 'remaining' => null];
        if ($event->getLimitationPerSwimmer() !== null && $nageuseId) {
            $status = $this->nageuseService->getSwimmerReservationStatus($eventId, $nageuseId);
        }

        return [
            'event' => $event,
            'session' => $session,
            'nageuse' => $nageuse,
            'tarifsById' => $tarifsById,
            'tarifs' => $tarifs,
            'limitation' => $reservationData['limitPerSwimmer'] ?? null,
            'placesDejaReservees' => $status['reserved'],
            'placesRestantes' => $status['remaining'],
            'tarifQuantities' => $this->getTarifQuantitiesFromDetails($reservationDetails, $tarifs),
            'specialTarifSession' => $this->tarifService->findSpecialTarifInDetails($reservationDetails, $tarifs),
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
     * Traite et valide la soumission de l'étape 3 (choix des tarifs).
     *
     * @param array $input Les données brutes du formulaire AJAX.
     * @param array $reservationData L'état actuel de la session de réservation.
     * @return array ['success' => bool, 'error' => ?string, 'data' => ?array]
     */
    public function processAndValidateStep3(array $input, array $reservationData): array
    {
        $eventId = $reservationData['event_id'] ?? null;
        if (!$eventId) {
            return ['success' => false, 'error' => 'Session expirée.'];
        }

        $allTarifs = $this->tarifsRepository->findByEventId($eventId);

        $tarifsById = [];
        foreach ($allTarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        // Préserver les anciens détails pour les noms/prénoms/places
        $oldDetails = $reservationData['reservation_detail'] ?? [];

        // Compter les nouvelles quantités demandées par tarif_id
        $newQuantities = [];
        foreach ($input['tarifs'] ?? [] as $t) {
            $id = (int)($t['id'] ?? 0);
            $qty = (int)($t['qty'] ?? 0);
            if ($qty > 0 && isset($tarifsById[$id])) {
                $placesPerTarif = $tarifsById[$id]->getNbPlace() ?? 1;
                $newQuantities[$id] = ($newQuantities[$id] ?? 0) + ($qty * $placesPerTarif);
            }
        }

        $updatedDetails = [];
        // D'abord, on essaie de conserver les participants existants
        foreach ($oldDetails as $oldDetail) {
            $tarifId = $oldDetail['tarif_id'];
            if (isset($newQuantities[$tarifId]) && $newQuantities[$tarifId] > 0) {
                // Ce participant est toujours valide, on le garde
                $updatedDetails[] = new ReservationDetailItemDTO(
                    tarif_id: $tarifId,
                    nom: $oldDetail['nom'] ?? null,
                    prenom: $oldDetail['prenom'] ?? null,
                    access_code: $oldDetail['access_code'] ?? null,
                    justificatif_name: $oldDetail['justificatif_name'] ?? null,
                    seat_id: $oldDetail['seat_id'] ?? null,
                    seat_name: $oldDetail['seat_name'] ?? null
                );
                $newQuantities[$tarifId]--; // On décrémente le besoin pour ce tarif
            }
        }

        // Ensuite, on ajoute les nouveaux participants pour les quantités restantes
        foreach ($newQuantities as $tarifId => $remainingQty) {
            // On cherche le code associé à ce tarif dans l'input initial
            $code = null;
            foreach ($input['tarifs'] as $t) { if (($t['id'] ?? 0) == $tarifId) { $code = $t['code'] ?? null; break; } }

            for ($i = 0; $i < $remainingQty; $i++) {
                $updatedDetails[] = new ReservationDetailItemDTO(tarif_id: $tarifId, access_code: $code);
            }
        }

        if (empty($updatedDetails)) {
            return ['success' => false, 'error' => 'Aucun tarif sélectionné.'];
        }

        return ['success' => true, 'data' => $updatedDetails];
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
     * Traite et valide la soumission de l'étape 4 (détails des participants et justificatifs).
     *
     * @param array $postData Données de $_POST (noms, prénoms).
     * @param array $filesData Données de $_FILES (justificatifs).
     * @param array $reservationData L'état actuel de la session de réservation.
     * @return array ['success' => bool, 'error' => ?string, 'data' => ?array<ReservationDetailItemDTO>]
     */
    public function processAndValidateStep4(array $postData, array $filesData, array $reservationData): array
    {
        $eventId = $reservationData['event_id'] ?? null;
        if (!$eventId) {
            return ['success' => false, 'error' => 'Session expirée.'];
        }

        $noms = $postData['noms'] ?? [];
        $prenoms = $postData['prenoms'] ?? [];
        $justificatifs = $filesData['justificatifs'] ?? null;

        // Valider que le nombre de noms/prénoms soumis correspond au nombre de participants attendus.
        $reservationDetails = $reservationData['reservation_detail'] ?? [];

        if (count($reservationDetails) !== count($noms) || count($noms) !== count($prenoms)) {
            return ['success' => false, 'error' => 'Incohérence du nombre de participants.'];
        }

        // Valider la qualité des noms/prénoms (non vides, pas identiques, pas de doublons).
        $couples = [];
        for ($i = 0; $i < count($noms); $i++) {
            $nom = trim($noms[$i]);
            $prenom = trim($prenoms[$i]);
            if ($nom === '' || $prenom === '') {
                return ['success' => false, 'error' => "Nom ou prénom manquant pour le participant " . ($i + 1)];
            }
            if (strtolower($nom) === strtolower($prenom)) {
                return ['success' => false, 'error' => "Le nom et le prénom du participant " . ($i + 1) . " doivent être différents."];
            }
            $key = strtolower($nom . '|' . $prenom);
            if (in_array($key, $couples, true)) {
                return ['success' => false, 'error' => "Le couple nom/prénom du participant " . ($i + 1) . " est déjà utilisé."];
            }
            $couples[] = $key;
        }

        // 3. Préparer les données pour le traitement (tarifs).
        $tarifs = $this->tarifsRepository->findByEventId($eventId);
        $tarifsById = [];
        foreach ($tarifs as $tarif) { $tarifsById[$tarif->getId()] = $tarif; }

        // 4. Reconstruire la liste des participants avec les nouvelles informations.
        $updatedDetails = [];
        $justifIndex = 0;

        foreach ($reservationDetails as $index => $detail) {
            $tarifId = $detail['tarif_id'];
            $tarif = $tarifsById[$tarifId] ?? null;

            // Crée un nouveau DTO avec les informations de base
            $newItem = new ReservationDetailItemDTO(
                tarif_id: $tarifId,
                nom: strtoupper($noms[$index]),
                prenom: ucwords(strtolower($prenoms[$index])),
                access_code: $detail['access_code'] ?? null,
                seat_id: $detail['seat_id'] ?? null,
                seat_name: $detail['seat_name'] ?? null
            );

            if ($tarif && $tarif->getIsProofRequired()) {
                $file = [
                    'name' => $justificatifs['name'][$justifIndex] ?? null,
                    'tmp_name' => $justificatifs['tmp_name'][$justifIndex] ?? null,
                    'error' => $justificatifs['error'][$justifIndex] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $justificatifs['size'][$justifIndex] ?? 0,
                ];

                if ($file['error'] === UPLOAD_ERR_OK) {
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $uniqueName = $this->generateUniqueProofName($noms[$index], $prenoms[$index], $tarifId, $extension);
                    $destination = __DIR__ . '/../..' . UPLOAD_PROOF_PATH . 'temp/';

                    $uploadResult = $this->uploadService->handleUpload($file, $destination, $uniqueName, [
                        'max_size_mb' => MAX_UPLOAD_PROOF_SIZE,
                        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
                        'allowed_mime_types' => ['application/pdf', 'image/jpeg', 'image/png']
                    ]);

                    if (!$uploadResult['success']) {
                        return ['success' => false, 'error' => "Participant " . ($index + 1) . ": " . $uploadResult['error']];
                    }
                    $newItem->justificatif_name = $uniqueName;

                } elseif (!empty($detail['justificatif_name'])) {
                    // Conserver le justificatif déjà présent en session
                    $newItem->justificatif_name = $detail['justificatif_name'];
                } else {
                    return ['success' => false, 'error' => "Justificatif manquant pour le participant: " . ($index + 1)];
                }

                $justifIndex++;
            }

            $updatedDetails[] = $newItem;
        }

        return ['success' => true, 'data' => $updatedDetails];
    }

    /**
     * Traite et valide la soumission de l'étape 5 (choix des places sur le plan).
     *
     * @param array $input
     * @param array $reservationData
     * @return array
     */
    public function processAndValidateStep5(array $input, array $reservationData): array
    {
        $sessionId = session_id();
        $seats = $input['seats'] ?? [];
        $nbPlacesAssises = count($reservationData['reservation_detail']);

        if (count($seats) !== $nbPlacesAssises) {
            return ['success' => false, 'error' => 'Nombre de places sélectionnées incorrect.'];
        }

        // Vérifier que chaque place est bien réservée temporairement pour cette session
        $tempSeats = $this->tempRepo->findAllSeatsBySession($sessionId) ?? [];
        $tempSeatIds = array_map(fn($t) => $t->getPlaceId(), $tempSeats);

        foreach ($seats as $seatId) {
            if (!in_array($seatId, $tempSeatIds)) {
                return ['success' => false, 'error' => "La place $seatId n'est pas réservée pour cette session."];
            }
        }

        //Pas besoin de mettre à jour $_SESSION, les places y sont déjà. On met juste à jour last_activity
        $_SESSION['reservation'][$sessionId]['last_activity'] = time();

        return ['success' => true, 'data' => $reservationData];
    }

    /**
     * Traite et valide la soumission de l'étape 6 (choix des compléments).
     *
     * @param array $input
     * @param array $reservationData
     * @return array
     */
    public function processAndValidateStep6(array $input, array $reservationData): array
    {
        // Récupérer tous les tarifs de l'événement pour la validation.
        $allEventTarifs = $this->tarifsRepository->findByEventId($reservationData['event_id']);

        // Créer une "liste blanche" d'IDs de tarifs qui sont des compléments (pas de place assise).
        $validComplementaryTarifIds = [];
        foreach ($allEventTarifs as $tarif) {
            if ($tarif->getNbPlace() === null) {
                $validComplementaryTarifIds[] = $tarif->getId();
            }
        }

        // Parcourir les articles soumis par l'utilisateur et ne conserver que les valides.
        $submittedItems = $input['tarifs'] ?? [];
        $validatedComplements = [];
        foreach ($submittedItems as $item) {
            $tarifId = (int)($item['id'] ?? 0);
            $quantity = (int)($item['qty'] ?? 0);
            // On ne garde l'article que si la quantité est positive et si le tarif
            // fait bien partie de notre liste blanche de compléments.
            if ($quantity > 0 && in_array($tarifId, $validComplementaryTarifIds, true)) {
                $validatedComplements[] = ['tarif_id' => $tarifId, 'qty' => $quantity];
            }
        }

        // Retourner le tableau propre des compléments validés.
        return ['success' => true, 'data' => $validatedComplements];
    }


    /**
     * Génère un nom de fichier unique pour un justificatif.
     *
     * @param string $nom
     * @param string $prenom
     * @param int $tarifId
     * @param string $extension
     * @return string
     */
    private function generateUniqueProofName(string $nom, string $prenom, int $tarifId, string $extension): string
    {
        $sessionId = session_id();
        $safeNom = strtolower(preg_replace('/[^a-z0-9]/i', '', $nom));
        $safePrenom = strtolower(preg_replace('/[^a-z0-9]/i', '', $prenom));
        return "{$sessionId}_{$tarifId}_{$safeNom}_{$safePrenom}.{$extension}";
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
            $return = $this->verifyPrerequisitesStep1($data['event_id'], $data['event_session_id'], $data['nageuse_id']);

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
            $return = $this->validatePayerInformationStep2($data);

            // Si la validation de l'étape 2 réussit, on la considère comme la donnée de retour principale.
            // Sinon, on retourne l'erreur.
            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 3) {
            $dataForStep3 = [];
            if ($step == 3) {
                // Pour la soumission de l'étape 3, on utilise les données du formulaire.
                $dataForStep3 = $dataInputed;
            } else {
                // Pour les étapes suivantes, on reconstruit un input de type "étape 3"
                // à partir des données déjà validées et stockées en session.
                $allEventTarifs = $this->tarifsRepository->findByEventId($reservationDataSession['event_id']);
                $tarifQuantities = $this->getTarifQuantitiesFromDetails($reservationDataSession['reservation_detail'] ?? [], $allEventTarifs);

                $reconstructedTarifs = [];
                foreach ($tarifQuantities as $tarifId => $qty) {
                    $reconstructedTarifs[] = ['id' => $tarifId, 'qty' => $qty];
                }
                // On ne reconstruit pas le 'code' ici, car la validation des codes spéciaux est déjà implicite dans les `reservation_detail` en session.
                $dataForStep3 = ['tarifs' => $reconstructedTarifs];
            }

            $return = $this->processAndValidateStep3($dataForStep3, $reservationDataSession);

            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 4) {
            $dataForStep4 = [];
            if ($step == 4) {
                // Pour la soumission de l'étape 4, on utilise les données du formulaire.
                $postData = $dataInputed[0] ?? [];
                $filesData = $dataInputed[1] ?? [];
                $return = $this->processAndValidateStep4($postData, $filesData, $reservationDataSession);
            } else {
                // Pour les étapes suivantes, on reconstruit les noms/prénoms à partir de la session.
                $details = $reservationDataSession['reservation_detail'] ?? [];
                $postData = [
                    'noms' => array_column($details, 'nom'),
                    'prenoms' => array_column($details, 'prenom')
                ];
                // On passe un tableau de fichiers vide, car on ne re-valide pas les uploads.
                $return = $this->processAndValidateStep4($postData, [], $reservationDataSession);
            }

            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 5) {
            $dataForStep5 = [];
            if ($step == 5) {
                // Pour la soumission de l'étape 5, on utilise les données du formulaire.
                $dataForStep5 = $dataInputed;
            } else {
                // Pour les étapes suivantes, on reconstruit la liste des places à partir de la session.
                $details = $reservationDataSession['reservation_detail'] ?? [];
                $seatIds = array_filter(array_column($details, 'seat_id'));
                $dataForStep5 = ['seats' => $seatIds];
            }

            $return = $this->processAndValidateStep5($dataForStep5, $reservationDataSession);

            if (!$return['success']) {
                return $return;
            }
        }

        if ($step >= 6) {
            // Pour l'étape 6, la re-validation n'est pas nécessaire, car elle n'a pas d'impact
            // sur les étapes précédentes et ne modifie pas les 'reservation_detail'.
            // On ne la valide que lors de sa soumission directe.
            if ($step == 6) {
                $return = $this->processAndValidateStep6($dataInputed, $reservationDataSession);
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
     * Indique le statut de la place dans la session de l'event en paramètre
     *
     * @param int $seatId
     * @param int $eventSessionId
     * @return array
     * @throws DateMalformedStringException
     */
    public function seatStatus(int $seatId, int $eventSessionId): array
    {
        // Vérifier que la place existe et est ouverte
        // 1. La place existe-t-elle et est-elle ouverte à la réservation ?
        $place = $this->placesRepository->findById($seatId);
        if (!$place || !$place->isOpen()) {
            return ['success' => false, 'error' => "Cette place n'est plus disponible.", 'reason' => 'closed', 'seat_id' => $seatId];
        }

        // 2. La place est-elle déjà réservée de manière définitive ?
        $placesReservees = $this->reservationsDetailsRepository->findReservedSeatsForSession($eventSessionId);
        if (in_array($seatId, $placesReservees)) {
            return ['success' => false, 'error' => "Cette place vient d'être réservée.", 'reason' => 'taken_definitively', 'seat_id' => $seatId];
        }

        // 3. La place est-elle déjà en cours de réservation par un autre utilisateur ?
        // On supprime d'abord les sessions expirées pour un contrôle fiable.
        $this->tempRepo->deleteExpired((new DateTime())->format('Y-m-d H:i:s'));
        $tempSeats = $this->tempRepo->findByEventSession($eventSessionId);
        foreach ($tempSeats as $t) {
            if ($t->getPlaceId() == $seatId && $t->getSession() !== session_id()) {
                return ['success' => false, 'error' => "Cette place est actuellement en cours de réservation par un autre utilisateur.", 'reason' => 'taken_temporarily', 'seat_id' => $seatId];
            }
        }

        return ['success' => true, 'error' => null, 'reason' => null, 'place' => $place];
    }

    /**
     * Prépare un "ViewModel" complet pour l'étape 5 (choix des places).
     *
     * @param array $reservationData
     * @return array|null
     * @throws DateMalformedStringException
     */
    public function getStep5ViewModel(array $reservationData): ?array
    {
        // Récupérer le contexte de base
        $baseViewModel = $this->getReservationViewModel($reservationData);
        if ($baseViewModel === null) {
            return null;
        }

        // Logique spécifique à l'étape 5
        $sessionId = session_id();
        $event = $baseViewModel['event'];

        // Suppression des réservations temporaires expirées
        $this->tempRepo->deleteExpired((new DateTime())->format('Y-m-d H:i:s'));

        // Récupérer les places temporairement réservées pour cette session d'événement
        $tempSeats = $this->tempRepo->findByEventSession($reservationData['event_session_id']);

        // Récupérer les places déjà réservées de manière définitive
        $placesReservees = $this->reservationsDetailsRepository->findReservedSeatsForSession($reservationData['event_session_id']);

        // Construire un tableau place_id ⇒ session_id pour la vue
        $placesSessions = [];
        foreach ($tempSeats as $t) {
            $placesSessions[$t->getPlaceId()] = $t->getSession();
            // Si la place temporaire appartient à la session en cours, on met à jour les détails en session
            if ($t->getSession() === $sessionId) {
                $place = $this->placesRepository->findById($t->getPlaceId());
                $reservationData['reservation_detail'][$t->getIndex()]['seat_id'] = $t->getPlaceId();
                $reservationData['reservation_detail'][$t->getIndex()]['seat_name'] = $place ? $place->getFullPlaceName() : $t->getPlaceId();
            }
        }
        // On met à jour la session avec les places récupérées
        $this->reservationSessionService->setReservationSession('reservation_detail', $reservationData['reservation_detail']);

        // Récupérer les zones et les places pour l'affichage du plan
        $zones = $this->zonesRepository->findOpenZonesByPiscine($event->getPiscine()->getId());
        $zonesWithPlaces = [];
        foreach ($zones as $zone) {
            $zonesWithPlaces[] = [
                'zone' => $zone,
                'places' => $this->placesRepository->findByZone($zone->getId())
            ];
        }

        // Fusionner et retourner toutes les données pour la vue
        return array_merge($baseViewModel, [
            'zonesWithPlaces' => $zonesWithPlaces,
            'placesReservees' => $placesReservees,
            'placesSessions' => $placesSessions,
            'nbPlacesAssises' => $this->countSeatedPlaces($reservationData['reservation_detail'], $baseViewModel['tarifs']),
            'reservation' => $this->reservationSessionService->getReservationSession() // On recharge la session au cas où elle a été modifiée
        ]);
    }

    /**
     * Calcule le montant total de la réservation en se basant sur les quantités de chaque tarif.
     *
     * @param array $reservationData Les données complètes de la session de réservation.
     * @return float Le montant total.
     */
    public function calculateTotalAmount(array $reservationData): float
    {
        $total = 0.0;
        $eventId = $reservationData['event_id'] ?? null;
        if (!$eventId) {
            return $total;
        }

        $allEventTarifs = $this->tarifsRepository->findByEventId($eventId);
        $tarifsById = [];
        foreach ($allEventTarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        // 1. Calcul pour les tarifs avec places assises (packs inclus)
        $reservationDetails = $reservationData['reservation_detail'] ?? [];
        $seatedTarifQuantities = $this->getTarifQuantitiesFromDetails($reservationDetails, $allEventTarifs);

        foreach ($seatedTarifQuantities as $tarifId => $quantity) {
            if (isset($tarifsById[$tarifId])) {
                $total += $quantity * $tarifsById[$tarifId]->getPrice();
            }
        }

        // 2. Calcul pour les compléments (tarifs sans place assise)
        $complements = $reservationData['reservation_complement'] ?? [];
        foreach ($complements as $complement) {
            $tarifId = $complement['tarif_id'];
            if (isset($tarifsById[$tarifId])) {
                $total += $complement['qty'] * $tarifsById[$tarifId]->getPrice();
            }
        }

        return $total;
    }

}