<?php

namespace app\Services\DataValidation;

use app\DTO\ReservationComplementItemDTO;
use app\DTO\ReservationDetailItemDTO;
use app\DTO\ReservationSelectionSessionDTO;
use app\DTO\ReservationUserDTO;
use app\Models\Event\Event;
use app\Models\Reservation\ReservationDetailTemp;
use app\Models\Reservation\ReservationTemp;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventTarifRepository;
use app\Repository\Reservation\ReservationComplementTempRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationDetailTempRepository;
use app\Repository\Reservation\ReservationTempRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Repository\Tarif\TarifRepository;
use app\Services\Event\EventQueryService;
use app\Services\FlashMessageService;
use app\Services\Reservation\ReservationDataPersist;
use app\Services\Reservation\ReservationQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;
use app\Services\Tarif\TarifService;
use app\Services\UploadService;
use DateTime;
use DateTimeImmutable;

class ReservationDataValidationService
{
    private EventRepository             $eventRepository;
    private SwimmerRepository           $swimmerRepository;
    private SwimmerQueryService         $swimmerQueryService;
    private ReservationSessionService   $reservationSessionService;
    private EventQueryService           $eventQueryService;
    private EventTarifRepository        $eventTarifRepository;
    private UploadService               $uploadService;
    private FlashMessageService         $flashMessageService;
    private ReservationDataPersist      $reservationDataPersist;
    private ReservationQueryService $reservationQueryService;
    private ReservationTempRepository $reservationTempRepository;
    private ReservationDetailRepository $reservationDetailRepository;
    private ReservationDetailTempRepository $reservationDetailTempRepository;
    private ReservationComplementTempRepository $reservationComplementTempRepository;
    private TarifRepository $tarifRepository;
    private TarifService $tarifService;

    public function __construct(
        EventRepository                  $eventRepository,
        SwimmerRepository                $swimmerRepository,
        SwimmerQueryService              $swimmerQueryService,
        ReservationSessionService        $reservationSessionService,
        EventQueryService                $eventQueryService,
        EventTarifRepository             $eventTarifRepository,
        UploadService                    $uploadService,
        FlashMessageService              $flashMessageService,
        ReservationDataPersist           $reservationDataPersist,
        ReservationQueryService          $reservationQueryService,
        ReservationTempRepository        $reservationTempRepository,
        ReservationDetailRepository      $reservationDetailRepository,
        ReservationDetailTempRepository  $reservationDetailTempRepository,
        ReservationComplementTempRepository $reservationComplementTempRepository,
        TarifRepository                  $tarifRepository,
        TarifService                     $tarifService
    ) {
        $this->eventRepository = $eventRepository;
        $this->swimmerRepository = $swimmerRepository;
        $this->swimmerQueryService = $swimmerQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->eventQueryService = $eventQueryService;
        $this->eventTarifRepository = $eventTarifRepository;
        $this->uploadService = $uploadService;
        $this->flashMessageService = $flashMessageService;
        $this->reservationDataPersist = $reservationDataPersist;
        $this->reservationQueryService = $reservationQueryService;
        $this->reservationTempRepository = $reservationTempRepository;
        $this->reservationDetailRepository = $reservationDetailRepository;
        $this->reservationDetailTempRepository = $reservationDetailTempRepository;
        $this->reservationComplementTempRepository = $reservationComplementTempRepository;
        $this->tarifRepository = $tarifRepository;
        $this->tarifService = $tarifService;
    }

    /**
     * Validation des données étape par étape et insertion/update des données.
     *
     * @param ReservationTemp|null $reservationTemp
     * @param int $step
     * @param array $data
     * @param array|null $file
     * @return array
     */
    public function validateDataPerStep(?ReservationTemp $reservationTemp, int $step, array $data, ?array $file = null): array
    {
        $sessionId = session_id();
        if ($step == 1) {
            //On construit l'objet et on valide les données dedans avec un retour success = true/false et errors
            $reservationTemp = new ReservationTemp();
            $reservationTemp->setSessionId($sessionId);
            $reservationTemp->setEvent((int)($data['event_id'] ?? 0));
            $reservationTemp->setEventSession((int)($data['event_session_id'] ?? 0));
            $reservationTemp->setSwimmerId(isset($data['swimmer_id']) ? (int)$data['swimmer_id'] : null);
            $reservationTemp->setAccessCode(isset($data['access_code']) ? (string)$data['access_code'] : null);

            $result = $this->validateDataStep1($reservationTemp);

            if (!$result['success']) {
                return ['success' => false, 'errors' => $result['errors'], 'data' => []];
            }
            //Il peut dans certain cas, comme une erreur entre l'insert et le changement de vue (erreur JS) peut déjà exister en table un enregistrement avec le même session_id()
            //On le supprime donc avant.
            if ($this->reservationTempRepository->findBySessionId($sessionId)) {
                $this->reservationTempRepository->deleteBySession($sessionId);
            }

            $this->reservationTempRepository->insert($reservationTemp);
        }

        if ($step == 2) {
            //On crée un objet temporaire pour cette étape
            $reservationTempForStep2 = clone $reservationTemp;
            $reservationTempForStep2->setName((string)$data['name'] ?? null);
            $reservationTempForStep2->setFirstname((string)$data['firstname'] ?? null);
            $reservationTempForStep2->setEmail((string)$data['email'] ?? null);
            $reservationTempForStep2->setPhone((string)$data['phone'] ?? null);

            $result = $this->validateDataStep2($reservationTempForStep2);

            if (!$result['success']) {
                return ['success' => false, 'errors' => $result['errors'], 'data' => []];
            }
            // On met à jour l'objet principal avec les données validées du clone
            $reservationTemp->setName($reservationTempForStep2->getName());
            $reservationTemp->setFirstName($reservationTempForStep2->getFirstName());
            $reservationTemp->setEmail($reservationTempForStep2->getEmail());
            $reservationTemp->setPhone($reservationTempForStep2->getPhone());
            $this->reservationTempRepository->update($reservationTemp);
        }

        if($step == 3) {
            //On récupère l'event pour avoir les tarifs
            $event = $this->eventRepository->findById($reservationTemp->getEvent());
            if (!$event) {
                return ['success' => false, 'errors' => ['event_id' => 'Événement introuvable.'], 'data' => []];
            }

            // Récupérer les détails existants et les regrouper par tarifId
            $existingDetailsRaw = $this->reservationDetailTempRepository->findByFields(['reservation_temp' => $reservationTemp->getId()]);
            $existingDetailsByTarif = [];
            foreach ($existingDetailsRaw as $detail) {
                $existingDetailsByTarif[$detail->getTarif()][] = $detail;
            }

            $finalDetails = [];
            $detailsToDelete = [];
            $detailsToInsert = [];

            $allTarifIds = array_unique(array_merge(array_keys($data['tarifs'] ?? []), array_keys($existingDetailsByTarif)));

            foreach ($allTarifIds as $tarifId) {
                $tarif = $this->tarifRepository->findById($tarifId);
                if (!$tarif || $tarif->getSeatCount() === null) { // On ne traite que les tarifs avec places
                    continue;
                }

                $desiredPackQty = (int)($data['tarifs'][$tarifId] ?? 0);
                $desiredSeatCount = $desiredPackQty * $tarif->getSeatCount();

                $existingTarifDetails = $existingDetailsByTarif[$tarifId] ?? [];
                $existingSeatCount = count($existingTarifDetails);

                if ($desiredSeatCount > $existingSeatCount) {
                    // --- AJOUT ---
                    // On garde les existants
                    $finalDetails = array_merge($finalDetails, $existingTarifDetails);
                    // On ajoute les nouveaux
                    $toAddCount = $desiredSeatCount - $existingSeatCount;
                    for ($i = 0; $i < $toAddCount; $i++) {
                        $newDetail = new ReservationDetailTemp();
                        $newDetail->setReservationTemp($reservationTemp->getId());
                        $newDetail->setTarif($tarifId);
                        // Gérer le code d'accès si applicable
                        if (isset($data['special'][$tarifId])) {
                            $newDetail->setTarifAccessCode($data['special'][$tarifId]);
                        }
                        $detailsToInsert[] = $newDetail;
                        $finalDetails[] = $newDetail;
                    }
                } elseif ($desiredSeatCount < $existingSeatCount) {
                    // --- SUPPRESSION ---
                    // On garde les premiers
                    $keptDetails = array_slice($existingTarifDetails, 0, $desiredSeatCount);
                    $finalDetails = array_merge($finalDetails, $keptDetails);
                    // On marque les autres pour suppression
                    $detailsToDelete = array_merge($detailsToDelete, array_slice($existingTarifDetails, $desiredSeatCount));
                } else {
                    // --- MAINTIEN ---
                    // Le nombre n'a pas changé, on garde tout
                    $finalDetails = array_merge($finalDetails, $existingTarifDetails);
                }
            }

            // Validation des données
            $result = $this->validateDataStep3($finalDetails, $event);

            if (!$result['success']) {
                return ['success' => false, 'errors' => $result['errors'], 'data' => []];
            }

            // Persistance en base de données dans une transaction
            try {
                $this->reservationDetailTempRepository->beginTransaction();

                foreach ($detailsToDelete as $detail) {
                    $this->reservationDetailTempRepository->delete($detail->getId());
                }
                foreach ($detailsToInsert as $detail) {
                    $this->reservationDetailTempRepository->insert($detail);
                }
                $this->reservationDetailTempRepository->commit();
            } catch (\Exception $e) {
                $this->reservationDetailTempRepository->rollBack();
                // Log l'erreur $e->getMessage()
                return ['success' => false, 'errors' => ['global' => 'Une erreur serveur est survenue lors de la mise à jour des places.'], 'data' => []];
            }
        }

        if($step == 4) {
            // Récupérer tous les détails existants pour cette réservation temporaire.
            $details = $this->reservationDetailTempRepository->findByFields(['reservation_temp' => $reservationTemp->getId()]);
            $detailsById = [];
            foreach ($details as $detail) {
                $detailsById[$detail->getId()] = $detail;
            }

            $errors = [];
            $detailsToUpdate = [];

            // Parcourir chaque participant soumis et mettre à jour l'objet correspondant.
            foreach ($data as $detailId => $participantData) {
                if (!isset($detailsById[$detailId])) continue; // Sécurité: on ne traite que les IDs attendus

                $detail = $detailsById[$detailId];
                $detail->setName($participantData['name'] ?? null);
                $detail->setFirstName($participantData['firstname'] ?? null);

                $tarif = $detail->getTarifObject();
                if ($tarif && $tarif->getRequiresProof()) {
                    // Un justificatif est requis. Vérifions si un nouveau fichier est uploadé.
                    if (isset($file['justificatifs']['error'][$detailId]) && $file['justificatifs']['error'][$detailId] === UPLOAD_ERR_OK) {
                        // Un nouveau fichier est fourni, on le traite.
                        $uploadedFile = [
                            'name' => $file['justificatifs']['name'][$detailId],
                            'type' => $file['justificatifs']['type'][$detailId],
                            'tmp_name' => $file['justificatifs']['tmp_name'][$detailId],
                            'error' => $file['justificatifs']['error'][$detailId],
                            'size' => $file['justificatifs']['size'][$detailId],
                        ];

                        $newFileName = $this->generateUniqueProofName($detail->getName(), $detail->getFirstName(), $detail->getTarif(), pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                        $uploadResult = $this->uploadService->handleUpload($uploadedFile, UPLOAD_PROOF_PATH . 'temp/', $newFileName);

                        if ($uploadResult['success']) {
                            $detail->setJustificatifName($newFileName);
                            $detail->setJustificatifOriginalName($uploadedFile['name']);
                        } else {
                            // L'upload a échoué, on ajoute une erreur spécifique.
                            $errors["justificatifs[{$detailId}]"] = $uploadResult['error'];
                        }
                    }
                }
                $detailsToUpdate[] = $detail;
            }

            // Valider les données de tous les participants.
            $validationResult = $this->validateDataStep4($detailsToUpdate);
            $errors = array_merge($errors, $validationResult['errors']);

            if (!empty($errors)) {
                return ['success' => false, 'errors' => $errors];
            }

            // Persister les modifications en base de données.
            foreach ($detailsToUpdate as $detailToUpdate) {
                $this->reservationDetailTempRepository->update($detailToUpdate);
            }
        }


        if (gettype($reservationTemp->getEvent()) == 'integer') {
            $isSeatsWithNumberInEventSwimmingPool = $this->eventRepository->findById($reservationTemp->getEvent(), true)->getPiscine()->getNumberedSeats();
        } else {
            $isSeatsWithNumberInEventSwimmingPool = false;
        }

        return ['success' => true, 'errors' => [], 'data' => ['numerated_seat' => $isSeatsWithNumberInEventSwimmingPool]];
    }

    /**
     * Étape 1: valide event, session, nageur ou code d'accès.
     * Retourne tableau ['success' => true/false, 'errors' => $errors[]]
     *
     * @param ReservationTemp $reservationTemp
     * @return array
     */
    public function validateDataStep1(ReservationTemp $reservationTemp): array
    {
        $errors = [];

        //On vérifie que l'événement existe bien
        $event = $this->eventRepository->findById($reservationTemp->getEvent(), true, true, true);
        if (!$event) {
            $errors['event_id'] = 'Événement introuvable.';
            return ['success' => false, 'errors' => $errors];
        }

        // On valide que la session fait bien partie de l'événement
        $selectedSession = null;
        foreach ($event->getSessions() as $eventSession) {
            if ($eventSession->getId() == $reservationTemp->getEventSession()) {
                $selectedSession = $eventSession;
                break;
            }
        }

        if (!$selectedSession) {
            $errors['event_session_id'] = 'Session introuvable pour cet événement.';
        } elseif ($selectedSession->getEventStartAt() < new DateTime()) {
            $errors['event_session_id'] = 'Cette session est déjà passée.';
        }

        //On compte tous spectateurs déjà confirmés (donc déjà payé).
        $nbSpectator = $this->reservationDetailRepository->countBySession($reservationTemp->getEventSession());

        //on vérifie que le quota n'est pas atteint
        if ($event->getPiscine()->getMaxPlaces() > 0 && $nbSpectator >= $event->getPiscine()->getMaxPlaces()) {
            $errors['event_session_id'] = 'Le maximum de spectateurs autorisés est atteint.';
        }

        // On valide la période d'inscription et le code d'accès
        $now = new DateTime();
        $openPeriods = [];
        $upcomingPeriods = [];
        foreach ($event->getInscriptionDates() as $period) {
            if ($period->getStartRegistrationAt() <= $now && $period->getCloseRegistrationAt() > $now) {
                $openPeriods[] = $period;
            } elseif ($period->getStartRegistrationAt() > $now) {
                $upcomingPeriods[] = $period;
            }
        }

        if (empty($openPeriods)) {
            $errors['access_code'] = 'Aucune période d\'inscription n\'est actuellement ouverte.';
            if (!empty($upcomingPeriods)) {
                //déjà trié par ordre de début
                $nextPeriod = $upcomingPeriods[0];
                $errors['access_code'] = 'Les inscriptions ouvriront le ' . $nextPeriod->getStartRegistrationAt()->format('d/m/Y à H:i') . '.';
            }
        } else {
            $accessCode = $reservationTemp->getAccessCode();
            $validPeriodFound = false;
            $isCodeRequired = false;

            foreach ($openPeriods as $period) {
                if ($period->getAccessCode() === null) {
                    $validPeriodFound = true;
                    break; // Une période ouverte sans code suffit
                }
                $isCodeRequired = true; // Au moins une période ouverte requiert un code
                if ($accessCode !== null && $period->getAccessCode() === $accessCode) {
                    $validPeriodFound = true;
                    break;
                }
            }

            if (!$validPeriodFound && $isCodeRequired) {
                $errors['access_code'] = 'Un code d\'accès valide est requis pour s\'inscrire maintenant.';
            }
        }

        //On vérifie si l'event a une limitation
        //Si oui, on vérifie si on a bien un swimmer_id
        if ($event->getLimitationPerSwimmer() !== null && $reservationTemp->getSwimmerId() === null) {
            $errors['swimmer_id'] = 'Le choix d\'une nageuse est requis pour cet événement.';
        } else if ($reservationTemp->getSwimmerId() !== null) {
            $swimmer = $this->swimmerRepository->findById($reservationTemp->getSwimmerId());
            if (!$swimmer) {
                $errors['swimmer_id'] = 'Nageuse invalide.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }


    /**
     * Étape 2: valide name, firstname, email, phone
     * Retourne tableau ['success' => true/false, 'errors' => $errors[]]
     *
     * @param ReservationTemp $reservationTemp
     * @return array
     */
    public function validateDataStep2(ReservationTemp $reservationTemp): array
    {
        $errors = [];

        $name = $reservationTemp->getName();
        $firstname = $reservationTemp->getFirstName();
        $email = $reservationTemp->getEmail();
        $phone = $reservationTemp->getPhone();

        //Vérification des données
        if (!empty($email)) {
            $candidate = trim($email);
            if (filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $email = $candidate;
            } else {
                $errors['email'] = 'Email invalide.';
            }
        } else {
            $errors['email'] = 'L\'email est obligatoire.';
        }

        if (!empty($phone)) {
            if (!preg_match('/^(?:0[1-9]\d{8}|\+33[1-9]\d{8})$/', str_replace(' ', '', $phone))) {
                $errors['phone'] = 'Numéro de téléphone invalide (doit être au format 0XXXXXXXXX ou +33XXXXXXXXX).';
            }
        }

        // Validation spécifique HelloAsso pour le nom et le prénom (uniquement si les champs sont remplis)
        $errors = array_merge($errors, $this->validateHelloAssoNameField('nom', $name));
        $errors = array_merge($errors, $this->validateHelloAssoNameField('prénom', $firstname));

        // Vérification finale : nom et prénom ne doivent pas être identiques
        if ($name && $firstname && mb_strtolower($name, 'UTF-8') === mb_strtolower($firstname, 'UTF-8')) {
            $errors['name'] = 'Le nom et le prénom ne peuvent pas être identiques.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Étape 3: valide tarif et code si besoin
     * Retourne tableau ['success' => true/false, 'errors' => $errors[]]
     *
     * @param ReservationDetailTemp[] $reservationDetailTempTab Tableau d'objets à valider.
     * @param Event $event
     * @return array
     */
    public function validateDataStep3(array $reservationDetailTempTab, Event $event): array
    {
        $errors = [];
        if (empty($reservationDetailTempTab)) {
            $errors['tarifs'] = 'Aucun tarif sélectionné.';
            return ['success' => false, 'errors' => $errors];
        }

        foreach ($reservationDetailTempTab as $reservationDetailTemp) {
            // On s'assure que chaque élément est bien du type attendu.
            if (!$reservationDetailTemp instanceof ReservationDetailTemp) {
                $errors['global'] = 'Une erreur interne est survenue lors de la validation des tarifs.';
                // On arrête la validation ici, car la structure de données est incorrecte.
                return ['success' => false, 'errors' => $errors];
            }
            //On vérifie que le tarif existe bien
            $tarif = $this->tarifRepository->findById($reservationDetailTemp->getTarif());
            if (!$tarif) {
                $errors['tarif_id'] = 'Ce tarif n\'existe pas.';
                return ['success' => false, 'errors' => $errors];
            }
            // On vérifie que le tarif fait bien partie de cet événement.
            if (!$this->tarifService->isTarifAssociatedWithEvent($tarif->getId(), $event->getId())) {
                $errors['tarif_id_' . $tarif->getId()] = "Le tarif '{$tarif->getName()}' n'est pas disponible pour cet événement.";
                return ['success' => false, 'errors' => $errors];
            }
            //On vérifie si le tarif nécessite un code eet si le bon est saisie
            if (
                $tarif->getAccessCode() !== null && // Si un code est requis pour ce tarif...
                ($reservationDetailTemp->getTarifAccessCode() === null || $reservationDetailTemp->getTarifAccessCode() !== $tarif->getAccessCode()) // ...et que le code fourni est soit manquant, soit incorrect.
            ) {
                $errors['tarif_id_' . $tarif->getId()] = "Le code d'accès fourni pour le tarif '{$tarif->getName()}' est invalide ou manquant.";
                return ['success' => false, 'errors' => $errors];
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Étape 4: valide name, firstname pour chaque tarif
     * Retourne tableau ['success' => true/false, 'errors' => $errors[]]
     *
     * @param ReservationDetailTemp[] $details
     * @return array
     */
    public function validateDataStep4(array $details): array
    {
        $errors = [];

        foreach ($details as $detail) {
            $participantErrors = [];
            $participantErrors = array_merge($participantErrors, $this->validateHelloAssoNameField("nom", $detail->getName()));
            $participantErrors = array_merge($participantErrors, $this->validateHelloAssoNameField("prénom", $detail->getFirstName()));

            // Vérifier si un justificatif est requis mais manquant
            $tarif = $detail->getTarifObject();
            if ($tarif && $tarif->getRequiresProof() && empty($detail->getJustificatifName())) {
                $participantErrors['justificatifs'] = 'Un justificatif est requis pour ce tarif.';
            }

            if (!empty($participantErrors)) {
                // Préfixer les clés d'erreur pour les faire correspondre aux champs du formulaire (ex: names[0], justificatifs[1])
                // On utilise l'ID du détail pour faire le lien avec le champ du formulaire.
                $detailId = $detail->getId();
                foreach ($participantErrors as $field => $message) {
                    $errors["{$field}[{$detailId}]"] = $message;
                }
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Validation étape par étape. L'étape courante avec le tableau $input et les précédentes avec le contenu de $_SESSION
     *
     * @param int $step
     * @param array $data
     * @param array|null $file
     * @return array
     */
    public function validateAndPersistDataPerStep(int $step, array $data, ?array $file = null): array
    {
        $dto = null;
        $dtos = null;
        //On récupère la session, à l'étape 1 elle doit être vide, existante.
        $session = $this->reservationSessionService->getReservationSession();
        if (!$session) {
            return ['success' => false, 'errors' => ['Erreur serveur'], 'data' => []];
        }

        // Valeurs par défaut issues de la session pour combler un payload incomplet
        $defaults = $this->reservationSessionService->getDefaultReservationStructure();
        $session = $this->reservationSessionService->getReservationSession() ?? [];

        if ($step === 1) {
            $effective = array_replace_recursive($defaults, $session, $data);
            $dto = ReservationSelectionSessionDTO::fromArray($effective);

            $check = $this->validateStep1($dto);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step === 2) {
            $effective = array_replace_recursive($defaults, $session);
            // On fusionne les données de $data dans la clé 'booker'
            $effective['booker'] = array_replace($effective['booker'], $data);

            $dto = ReservationUserDTO::fromArray($effective);

            $check = $this->validateStep2($dto);
            if ($check['success'] === false) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step === 3) {
            $effective = array_replace_recursive($defaults, $session);

            //Construction des DTOs
            $dtos = $this->builtDtos('ReservationDetailItemDTO', $effective, $data);

            // On rejette si vide
            if (empty($dtos)) {
                return ['success' => false, 'errors' => ['tarifs' => 'Aucun tarif sélectionné.'], 'data' => []];
            }

        }

        if ($step === 4) {
            $effective = array_replace_recursive($defaults, $session);
            //On récupère tous les tarifs de l'event
            $allEventTarifs = $this->eventTarifRepository->findTarifsByEvent($effective['event_id']);

            //on boucle sur reservation_detail
            //Initialise pour que les valeurs ne soient pas gardées dans les autres boucles
            $dtos = [];
            $newFileName = null;
            foreach ($effective['reservation_detail'] as $key => $detail) {
                if (isset($allEventTarifs[$detail['tarif_id']])) {

                    //On vérifie si le tarif est actif
                    if ($allEventTarifs[$detail['tarif_id']]->isActive() === false) {
                        return ['success' => false, 'errors' => ['message' => 'Ce tarif est inactif'], 'data' => []];
                    }

                    //On vérifie s'il y a besoin d'un code et s'il est fourni
                    if ($allEventTarifs[$detail['tarif_id']]->getAccessCode() !== null) {
                        if (isset($detail['access_code']) && $detail['access_code'] !== $allEventTarifs[$detail['tarif_id']]->getAccessCode()) {
                            return ['success' => false, 'errors' => ['message' => 'Code d\'accès invalide'], 'data' => []];
                        }
                    }
                }

                //On vérifie s'il y a besoin d'un justificatif
                if ($allEventTarifs[$detail['tarif_id']]->getRequiresProof() === true) {
                    //On vérifie d'abord si pas dans $session si on est dans le cas d'un retour en arrière pour revalider le formulaire.
                    $checkIfFileExisteAfterUpload = false;
                    if (isset($session['reservation_detail'][$key]['justificatif_name'])) {
                        //On vérifie si le fichier existe bien dans le dossier
                        $justificatif_name = $session['reservation_detail'][$key]['justificatif_name'];
                        $checkIfFileExisteAfterUpload = $this->uploadService->checkIfFileExisteAfterUpload(UPLOAD_PROOF_PATH . 'temp/', $justificatif_name);
                        $newFileName = $justificatif_name;
                        $orignalFileName = $session['reservation_detail'][$key]['justificatif_original_name'];
                    }
                    if ($checkIfFileExisteAfterUpload === false) {
                        if (
                            !isset($data[$key]['justificatif']) ||
                            !isset($file['justificatif_' . $key])
                        ) {
                            return ['success' => false, 'errors' => ['message' => 'Aucun justificatif fourni'], 'data' => []];
                        }
                        //On gère l'upload ici
                        $newFileName = $this->generateUniqueProofName(
                            $data[$key]['name'],
                            $data[$key]['firstname'],
                            $detail['tarif_id'],
                            strtolower(pathinfo($file['justificatif_' . $key]['name'], PATHINFO_EXTENSION))
                        );
                        $orignalFileName = $file['justificatif_' . $key]['name'];
                        $retourUpload = $this->uploadService->handleUpload(
                            $file['justificatif_' . $key],
                            UPLOAD_PROOF_PATH . 'temp/',
                            $newFileName
                        );
                        if (!$retourUpload) {
                            return ['success' => false, 'errors' => ['message' => 'Erreur lors de l\'upload du justificatif'], 'data' => []];
                        }
                    }
                }

                //Si c'est bon, on génère le DTO
                // Crée un nouveau DTO avec les informations de base
                $dtos[] = ReservationDetailItemDTO::fromArray(
                    [
                        'tarif_id' => $detail['tarif_id'],
                        'name' => $data[$key]['name'],
                        'firstname' => $data[$key]['firstname'],
                        'justificatif_name' => $newFileName ?? null,
                        'justificatif_original_name' => $orignalFileName ?? null,
                        'tarif_access_code' => $detail['tarif_access_code'] ?? null,
                    ]
                );
                //On réinitialise la valeur et on supprime de 'reservation_detail' pour éviter les doublons à l'ajout après
                $newFileName = null;
                unset($_SESSION['reservation']['reservation_detail'][$key]);

            }
            // On rejette si vide
            if (empty($dtos)) {
                return ['success' => false, 'errors' => ['message' => 'Aucun tarif sélectionné.'], 'data' => []];
            }
        }

        if ($step === 5) {

        }


        if ($step === 6) {
            $effective = array_replace_recursive($defaults, $session);

            $dtos = $this->builtDtos('ReservationComplementItemDTO', $effective, $data);

            //On ne rejette pas si vide puisqu'on n'oblige pas à prendre des compléments.
        }



        if ($dto === null && $dtos === null) {
            return ['success' => false, 'errors' => ['message' => 'Aucune étape ne correspond.'], 'data' => []];
        }
        //Une fois les données validées, on persiste
        if ($dto !== null) {
            $this->reservationDataPersist->persistDataInSession($dto);
        } else {
            foreach ($dtos as $key => $dtoItem) {
                $this->reservationDataPersist->persistDataInSession($dtoItem, $key);
            }
        }

        if (gettype($session['event_id']) == 'integer') {
            $isSeatsWithNumberInEventSwimmingPool = $this->eventRepository->findById($session['event_id'], true)->getPiscine()->getNumberedSeats();
        } else {
            $isSeatsWithNumberInEventSwimmingPool = false;
        }

        return ['success' => true, 'errors' => [], 'data' => ['numerated_seat' => $isSeatsWithNumberInEventSwimmingPool]];
    }

    /**
     * Pour revérifier les étapes précédentes
     *
     * @param int $step
     * @param array $session
     * @return array
     */
    public function checkPreviousStep(int $step, array $session): array
    {
        //On valide l'étape 1, on return si false
        if ($step > 1) {
            $check = $this->validateDataStep1($session['reservation']);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 2) {
            $check = $this->validateDataStep2($session['reservation']);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 3) {
            $event = $this->eventRepository->findById($session['reservation']->getEvent());
            $check = $this->validateDataStep3($session['reservation_details'], $event);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 4) {
            $event = $this->eventRepository->findById($session['reservation']->getEvent());
            $check = $this->validateDataStep4($session['reservation_details']);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 5 && $session['reservation']->getEventObject()->getPiscine()->getNumberedSeats() === true) {

        }

        $effective = [];
        if ($step > 6) {
            $dt6 = ReservationComplementItemDTO::fromArray($effective);
            $check = $this->validateStep6($dt6);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        return ['success' => true];
    }

    public function validateAllPreviousStep($session): bool
    {
        $eventId = (int)($session['event_id'] ?? 0);
        //On va chercher l'Event avec la piscine pour savoir s'il faut vérifier les places numérotées
        $event = $this->eventRepository->findById($eventId, true);

        //on fait la vérification des données de toutes les étapes
        for ($i = 1; $i <= 6;$i++) {
            if ($i == 5 && $event->getPiscine()->getNumberedSeats() === false) {
                continue;
            }
            if (!$this->checkPreviousStep($i, $session)) {
                $this->flashMessageService->setFlashMessage('danger', 'Erreur ' . $i . ' dans le parcours, veuillez recommencer');
                return false;
            }
        }

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

        // On récupère Event avec les dépendances nécessaires
        $event = $this->eventRepository->findById($dto->eventId, false, true, true);
        if (!$event) {
            $errors['eventId'] = 'Événement introuvable.';
        }

        // On vérifie que la session choisie existe bien et est bien rattachée à l'Event
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

        // Vérification du code si besoin pour la période d'inscription
        if ($event && empty($errors)) {
            //On vérifie si la période d'inscription nécessite un code
            $periodsStatus = $this->eventQueryService->getEventInscriptionPeriodsStatus([$event]);
            $activePeriod = $periodsStatus['periodesOuvertes'][$event->getId()] ?? null;
            //Si période active avec un code access
            if ($activePeriod && $activePeriod->getAccessCode() !== null) {
                if (trim((string)$dto->access_code) === '') {
                    $errors['accessCode'] = 'Un code d\'accès est requis pour la période d\'inscription en cours.';
                } else {
                    //On vérifie si le code saisi est valide
                    $check = $this->eventQueryService->validateAccessCode($event->getId(), htmlspecialchars((string)$dto->access_code));
                    if (!($check['success'] ?? false)) {
                        $errors['accessCode'] = $check['error'] ?? 'Code d\'accès invalide.';
                    }
                }
            }
        }


        // Nageur (si limitation active)
        $limit = $event?->getLimitationPerSwimmer();
        $access_code = $dto->access_code ? trim($dto->access_code) : null;

        if ($limit !== null && $dto->swimmerId === null && $access_code === null) {
            $errors['swimmerOrAccessCode'] = 'Le choix d\'une nageuse est requis pour cet événement.';
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

        // Validation spécifique HelloAsso pour le nom et le prénom
        if (empty($errors)) {
            $errors = array_merge($errors, $this->validateHelloAssoNameField('nom', $dto->name));
            $errors = array_merge($errors, $this->validateHelloAssoNameField('prénom', $dto->firstname));

            // Vérification finale : nom et prénom ne doivent pas être identiques
            if (empty($errors) && mb_strtolower($dto->name, 'UTF-8') === mb_strtolower($dto->firstname, 'UTF-8')) {
                $errors['name'] = 'Le nom et le prénom ne peuvent pas être identiques.';
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        return ['success' => true, 'errors' => []];
    }

    /**
     * Valide un champ (nom ou prénom) selon les règles spécifiques de HelloAsso.
     *
     * @param string $fieldName Le nom du champ pour les messages d'erreur (ex: 'nom', 'prénom').
     * @param string|null $value La valeur à valider.
     * @return array Les erreurs trouvées.
     */
    private function validateHelloAssoNameField(string $fieldName, ?string $value): array
    {
        $errors = [];

        if (empty($value)) {
            $errors[$fieldName] = "Le champ $fieldName est obligatoire.";
            return $errors;
        }

        $lowerValue = mb_strtolower($value, 'UTF-8');

        // Liste des valeurs interdites
        $forbiddenValues = [
            "firstname", "lastname", "unknown", "first_name", "last_name",
            "anonyme", "user", "admin", "name", "nom", "prénom", "test"
        ];

        // Règle : 3 caractères répétitifs (ex: "aaa")
        if (preg_match('/(.)\1{2,}/iu', $value)) {
            $errors[$fieldName] = "Le champ $fieldName ne peut pas contenir 3 caractères identiques à la suite.";
        }
        // Règle : Pas de chiffre
        elseif (preg_match('/\d/', $value)) {
            $errors[$fieldName] = "Le champ $fieldName ne peut pas contenir de chiffres.";
        }
        // Règle : Pas un seul caractère
        elseif (mb_strlen($value, 'UTF-8') < 2) {
            $errors[$fieldName] = "Le champ $fieldName doit contenir au moins 2 caractères.";
        }
        // Règle : Doit contenir au moins une voyelle (y compris accentuées)
        elseif (!preg_match('/[aeiouyàáâãäåæçèéêëìíîïòóôõöøùúûüýÿ]/iu', $value)) {
            $errors[$fieldName] = "Le champ $fieldName doit contenir au moins une voyelle.";
        }
        // Règle : Ne doit pas être une valeur interdite
        elseif (in_array($lowerValue, $forbiddenValues, true)) {
            $errors[$fieldName] = "La valeur '$value' n'est pas autorisée pour le champ $fieldName.";
        }
        // Règle : Caractères autorisés (alphabet latin, accents courants, apostrophe, tiret, cédille, espace)
        elseif (!preg_match('/^[a-zàáâãäåæçèéêëìíîïòóôõöøùúûüýÿ\'\-\s]+$/iu', $value)) {
            $errors[$fieldName] = "Le champ $fieldName contient des caractères non autorisés.";
        }

        return $errors;
    }


    /**
     * @param $dto
     * @return array
     */
    public function validateStep3($dto): array
    {
        $errors = [];

        // Récupération de la session et des détails saisis à l’étape 3
        $session  = $this->reservationSessionService->getReservationSession() ?? [];
        $eventId  = (int)($session['event_id'] ?? 0);
        $details  = $session['reservation_detail'] ?? [];

        if ($eventId <= 0) {
            $errors['event_id'] = 'Événement incohérent.';
        }

        if (empty($details) || !is_array($details)) {
            $errors['tarifs'] = 'Aucun tarif sélectionné.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Tarifs disponibles pour l’événement, indexés par ID
        $tarifsById = $this->eventTarifRepository->findTarifsByEvent($eventId);

        foreach ($details as $idx => $d) {
            $tid = (int)($d['tarif_id'] ?? 0);

            if ($tid <= 0 || !isset($tarifsById[$tid])) {
                $errors["reservation_detail.$idx.tarif_id"] = 'Tarif inconnu pour cet événement.';
                continue;
            }

            $tarif = $tarifsById[$tid];

            // Tarif actif
            if (!$tarif->isActive()) {
                $errors["reservation_detail.$idx.tarif_id"] = 'Tarif inactif.';
            }

            // Validation éventuelle du code d’accès requis
            $requiredCode = $tarif->getAccessCode();
            if ($requiredCode !== null) {
                $provided = isset($d['tarif_access_code']) ? trim((string)$d['tarif_access_code']) : '';
                if ($provided === '' || $provided !== $requiredCode) {
                    $errors["reservation_detail.$idx.tarif_access_code"] = 'Code d\'accès invalide pour ce tarif.';
                }
            }
        }

        //On revérifie si il y a assez de places disponibles
        $totalCapacityLimit = $this->reservationQueryService->checkTotalCapacityLimit($session['event_id'], $session['event_session_id']);
        if ($totalCapacityLimit['limitReached']) {
                $errors['limitReached'] = 'La capacité maximale de la piscine est atteinte.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * @param $dto
     * @return array
     */
    public function validateStep6($dto): array
    {
        $errors = [];

        // Récupération de la session et des détails saisis à l’étape 6
        $session  = $this->reservationSessionService->getReservationSession() ?? [];
        $eventId  = (int)($session['event_id'] ?? 0);
        $complements  = $session['reservation_complement'] ?? [];

        if ($eventId <= 0) {
            $errors['event_id'] = 'Événement incohérent.';
        }

        if (empty($complements) || !is_array($complements)) {
            $errors['tarifs'] = 'Aucun tarif sélectionné.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Tarifs disponibles pour l’événement, indexés par ID
        $tarifsById = $this->eventTarifRepository->findTarifsByEvent($eventId);

        foreach ($complements as $idx => $d) {
            $tid = (int)($d['tarif_id'] ?? 0);

            if ($tid <= 0 || !isset($tarifsById[$tid])) {
                $errors["reservation_complement.$idx.tarif_id"] = 'Tarif inconnu pour cet événement.';
                continue;
            }

            $tarif = $tarifsById[$tid];

            // Tarif actif
            if (!$tarif->isActive()) {
                $errors["reservation_complement.$idx.tarif_id"] = 'Tarif inactif.';
            }

            // Validation éventuelle du code d’accès requis
            $requiredCode = $tarif->getAccessCode();
            if ($requiredCode !== null) {
                $provided = isset($d['tarif_access_code']) ? trim((string)$d['tarif_access_code']) : '';
                if ($provided === '' || $provided !== $requiredCode) {
                    $errors["reservation_complement.$idx.tarif_access_code"] = 'Code d\'accès invalide pour ce tarif.';
                }
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }


    /**
     * Génère un nom de fichier unique pour un fichier.
     *
     * @param string $name
     * @param string $firstname
     * @param int $tarifId
     * @param string $extension
     * @return string
     */
    private function generateUniqueProofName(string $name, string $firstname, int $tarifId, string $extension): string
    {
        // Horodatage au fuseau horaire de l'application (ex : 20251005130632)
        $now = new DateTimeImmutable('now');
        $timestamp = $now->format('YmdHis');

        $normalizer = \Normalizer::class;
        $name = $normalizer::normalize($name, \Normalizer::FORM_D);
        $firstname = $normalizer::normalize($firstname, \Normalizer::FORM_D);
        $safeNom = strtolower(preg_replace('/[^a-z0-9]/i', '', $name ?? ''));
        $safePrenom = strtolower(preg_replace('/[^a-z0-9]/i', '', $firstname ?? ''));

        return "{$timestamp}_{$tarifId}_{$safeNom}_$safePrenom.$extension";
    }


    /**
     * Indexe le tableau $effective par tarif_id.
     * Accepte un tableau plat ou déjà indexé.
     *
     * @param array<int|string,mixed> $effective
     * @return array<int,array<string,mixed>>
     */
    private function mapEffectiveByTarifId(array $effective): array
    {
        $out = [];
        foreach ($effective as $row) {
            if (!is_array($row) || !isset($row['tarif_id'])) {
                continue;
            }
            $id = (int)$row['tarif_id'];
            $row['tarif_id'] = $id;
            $out[$id] ??= [];
            $out[$id][] = $row;
        }
        return $out;
    }

    /**
     * Complète un DTO avec les champs existants (par tarif_id) quand ils sont absents/vides dans le DTO.
     * N’écrase pas une valeur déjà renseignée par l’utilisateur.
     *
     * @template T of object
     * @param object $dto
     * @param array<int,array<string,mixed>> $effectiveByTarif
     * @return object
     */
    private function mergeDtoWithEffective(object $dto, array $effectiveByTarif): object
    {
        //On fait un tableau du DTO existant (avec les données de l'étape), sans les données déjà saisies dans les étapes suivantes.
        $dtoTab = get_object_vars($dto);

        //On cherche si tarif_id de $dtoTab existe en clé dans $effectiveByTarif
        if (array_key_exists($dtoTab['tarif_id'], $effectiveByTarif)) {
            //Si un champ vide ou null de $dtoTab existe dans une occurrence de $effectiveByTarif[$dtoTab['tarif_id']], on écrase $dtoTab avec $effectiveByTarif
            foreach ($effectiveByTarif[$dtoTab['tarif_id']] as $value) {
                $dtoTab = array_merge($dtoTab, $value);
            }
        }
        if ($dto instanceof ReservationDetailItemDTO) {
            $dto = ReservationDetailItemDTO::fromArray($dtoTab);
        } elseif ($dto instanceof ReservationComplementItemDTO) {
            // ...
        }

        return $dto;
    }

    private function builtDtos(string $DTOClass, array $effectiveSession, array $data): array
    {
        if ($DTOClass == 'ReservationDetailItemDTO') {
            $keySession = 'reservation_detail';
            //On récupère tous les tarifs de l'event
            $allEventTarifs = $this->eventTarifRepository->findTarifsByEvent($effectiveSession['event_id'], true);
        } else {
            $keySession = 'reservation_complement';
            //On récupère tous les tarifs de l'event
            $allEventTarifs = $this->eventTarifRepository->findTarifsByEvent($effectiveSession['event_id'], false);
        }

        //Boucle sur les tarifs reçus $data['tarifs']
        $dtos = [];
        foreach ($data['tarifs'] as $tarif_id => $qty) {
            //On boucle sur la quantité de ce tarif
            for ($i = 0; $i < $qty; $i++) {
            //Puis sur le nombre de places dans le tarif (pour les packs multi-places) pour avoir le nombre de places total
                if ($DTOClass == 'ReservationDetailItemDTO') {
                    $nbPlacesInPack = $allEventTarifs[$tarif_id]->getSeatCount();
                    for ($j = 0; $j < $nbPlacesInPack; $j++) {
                        //On génère le dto qu'on ajoute
                        $dtos[] = ReservationDetailItemDTO::fromArrayWithSpecialPrice($tarif_id, $data);
                    }
                } else {
                    //On va récupérer le code s'il y en a 1
                    $tarif = $allEventTarifs[$tarif_id];
                    $code = $tarif->getAccessCode();
                    $dtos[] = ReservationComplementItemDTO::fromArrayWithSpecialPrice($tarif_id, $data, $code);
                }
            }
        }

        //Pour ne pas perdre ce qui aurait été saisi avant
        // Indexer l'existant par tarif_id
        $effectiveByTarif = $this->mapEffectiveByTarifId($effectiveSession[$keySession]);

        // Filtrer les anciens détails pour ne garder que ceux présents dans les nouveaux dtos
        $tarifIdsActuels = array_map(fn($dto) => $dto->tarif_id, $dtos);
        $effectiveByTarif = array_filter(
            $effectiveByTarif,
            fn($tarifDetails, $tarifId) => in_array($tarifId, $tarifIdsActuels),
            ARRAY_FILTER_USE_BOTH
        );

        // Compléter chaque DTO construit depuis le POST avec l'existant si même tarif_id
        foreach ($dtos as $i => $dtoToComplete) {

            $dtos[$i] = $this->mergeDtoWithEffective($dtoToComplete, $effectiveByTarif);
        }

        $eventIdPayload = (int)($data['event_id'] ?? 0);
        $eventIdSession = (int)($effectiveSession['event_id'] ?? 0);
        if ($eventIdPayload <= 0 || $eventIdPayload !== $eventIdSession) {
            return ['success' => false, 'errors' => ['event_id' => 'Événement incohérent.'], 'data' => []];
        }

        //Les dtos sont prêts à être persistés,
        // on supprime ['reservation_retail'] ou ['reservation_complement'] pour éviter de garder des items supprimés par le visiteur.
        $this->reservationSessionService->setReservationSession($keySession, []);

        return $dtos;
    }

}
