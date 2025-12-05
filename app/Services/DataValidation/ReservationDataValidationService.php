<?php

namespace app\Services\DataValidation;

use app\Models\Event\Event;
use app\Models\Reservation\ReservationComplementTemp;
use app\Models\Reservation\ReservationDetailTemp;
use app\Models\Reservation\ReservationTemp;
use app\Repository\Event\EventRepository;
use app\Repository\Reservation\ReservationComplementTempRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationDetailTempRepository;
use app\Repository\Reservation\ReservationTempRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Repository\Tarif\TarifRepository;
use app\Services\FlashMessageService;
use app\Services\Tarif\TarifService;
use app\Services\UploadService;
use app\Utils\StringHelper;
use DateTime;
use Exception;

class ReservationDataValidationService
{
    private EventRepository             $eventRepository;
    private SwimmerRepository           $swimmerRepository;
    private UploadService               $uploadService;
    private FlashMessageService         $flashMessageService;
    private ReservationTempRepository   $reservationTempRepository;
    private ReservationDetailRepository $reservationDetailRepository;
    private ReservationDetailTempRepository $reservationDetailTempRepository;
    private ReservationComplementTempRepository $reservationComplementTempRepository;
    private TarifRepository             $tarifRepository;
    private TarifService                $tarifService;

    public function __construct(
        EventRepository                  $eventRepository,
        SwimmerRepository                $swimmerRepository,
        UploadService                    $uploadService,
        FlashMessageService              $flashMessageService,
        ReservationTempRepository        $reservationTempRepository,
        ReservationDetailRepository      $reservationDetailRepository,
        ReservationDetailTempRepository  $reservationDetailTempRepository,
        ReservationComplementTempRepository $reservationComplementTempRepository,
        TarifRepository                  $tarifRepository,
        TarifService                     $tarifService
    ) {
        $this->eventRepository = $eventRepository;
        $this->swimmerRepository = $swimmerRepository;
        $this->uploadService = $uploadService;
        $this->flashMessageService = $flashMessageService;
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

        //On récupère l'event pour avoir les tarifs après l'étape 1.
        $event = $this->eventRepository->findById($reservationTemp->getEvent());
        if (!$event) {
            return ['success' => false, 'errors' => ['event_id' => 'Événement introuvable.'], 'data' => []];
        }

        if ($step == 2) {
            //On crée un objet temporaire pour cette étape
            $reservationTemp->setName((string)$data['name'] ?? null);
            $reservationTemp->setFirstname((string)$data['firstname'] ?? null);
            $reservationTemp->setEmail((string)$data['email'] ?? null);
            $reservationTemp->setPhone((string)$data['phone'] ?? null);

            $result = $this->validateDataStep2($reservationTemp);

            if (!$result['success']) {
                return ['success' => false, 'errors' => $result['errors'], 'data' => []];
            }
            // On met à jour l'objet avec les données validées
            $this->reservationTempRepository->update($reservationTemp);
        }

        if($step == 3) {
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
            } catch (Exception $e) {
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
                if (!isset($detailsById[$detailId])) continue; // On ne traite que les IDs attendus

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

                        $newFileName = StringHelper::generateUniqueProofName($detail->getName(), $detail->getFirstName(), $detail->getTarif(), pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
                        $uploadResult = $this->uploadService->handleUpload($uploadedFile, UPLOAD_PROOF_PATH . 'temp/', $newFileName);

                        if ($uploadResult['success']) {
                            $detail->setJustificatifName($newFileName);
                            $detail->setJustificatifOriginalName($uploadedFile['name']);
                        } else {
                            // L'upload a échoué, on ajoute une erreur spécifique.
                            $errors["justificatifs[$detailId]"] = $uploadResult['error'];
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

        //Cette étape n'est présente que pour les piscines avec places numérotées
        if($step == 5 && $reservationTemp->getEventObject()->getPiscine()->getNumberedSeats()) {
            // Récupérer tous les détails existants pour cette réservation temporaire.
            $details = $this->reservationDetailTempRepository->findByFields(['reservation_temp' => $reservationTemp->getId()]);
            $validationResult = $this->validateDataStep5($details);

            if (!empty($validationResult['errors'])) {
                return ['success' => false, 'errors' => $validationResult['errors']];
            }
        }

        if($step == 6) {
            $existingComplementsRaw = $this->reservationComplementTempRepository->findByFields(['reservation_temp' => $reservationTemp->getId()]);
            $existingComplementsByTarif = [];
            foreach ($existingComplementsRaw as $complement) {
                $existingComplementsByTarif[$complement->getTarif()] = $complement;
            }

            $finalComplements = [];
            $complementsToDelete = [];
            $complementsToInsert = [];
            $complementToUpdate = [];

            $allTarifIds = array_unique(array_merge(array_keys($data['tarifs'] ?? []), array_keys($existingComplementsByTarif)));
            //On boucle sur tous les tarifs
            //Si l'ID du tarif existe dans $existingComplementsByTarif et dans $date, on update
            //Si l'ID du tarif n'existe pas dans $existingComplementsByTarif mais existe dans $data, on insert
            //Si l'ID du tarif existe dans $existingComplementsByTarif mais pas dans $data, on delete
            foreach ($allTarifIds as $tarifId) {
                //Code spécial si fourni
                $providedSpecial = is_array($data['special'] ?? null) ? ($data['special'][$tarifId] ?? null) : null;
                //Quantité demandée
                $providedQty = (int)($data['tarifs'][$tarifId] ?? 0);
                //Si existe dans les deux tableaux, on update s'il y a des différences
                if (array_key_exists($tarifId, $existingComplementsByTarif) && array_key_exists($tarifId, $data['tarifs'])) {
                    //On compare les codes spéciaux et la quantité
                    $existingComplementTemp = $existingComplementsByTarif[$tarifId];
                    if ($existingComplementTemp->getQty() !== $data['tarifs'][$tarifId]) {
                        $existingComplementTemp->setTarifAccessCode($providedSpecial);
                        $existingComplementTemp->setQty($providedQty);
                        $complementToUpdate[] = $existingComplementTemp;
                    }
                    //On enregistre pour la vérification finale
                    $finalComplements[] = $existingComplementTemp;
                }
                //n'existe pas encore, mais est dans $data, on insert
                if (!array_key_exists($tarifId, $existingComplementsByTarif) && array_key_exists($tarifId, $data['tarifs'])) {
                    $newComplement = new ReservationComplementTemp();
                    $newComplement->setReservationTemp($reservationTemp->getId());
                    $newComplement->setTarif($tarifId);
                    $newComplement->setQty($providedQty);
                    $newComplement->setTarifAccessCode($providedSpecial);
                    $complementsToInsert[] = $newComplement;
                    $finalComplements[] = $newComplement;
                }
                //existe, mais n'est plus dans $data, on delete
                if (array_key_exists($tarifId, $existingComplementsByTarif) && !array_key_exists($tarifId, $data['tarifs'])) {
                    $complementsToDelete[] = $existingComplementsByTarif[$tarifId];
                }
            }

            // Validation des données
            $result = $this->validateDataStep6($finalComplements, $event);

            if (!$result['success']) {
                return ['success' => false, 'errors' => $result['errors'], 'data' => []];
            }

            // Persistance en base de données dans une transaction
            try {
                $this->reservationComplementTempRepository->beginTransaction();

                foreach ($complementsToDelete as $complement) {
                    $this->reservationComplementTempRepository->delete($complement->getId());
                }
                foreach ($complementsToInsert as $complement) {
                    $this->reservationComplementTempRepository->insert($complement);
                }
                foreach ($complementToUpdate as $complement) {
                    $this->reservationComplementTempRepository->update($complement);
                }
                $this->reservationComplementTempRepository->commit();
            } catch (Exception $e) {
                $this->reservationComplementTempRepository->rollBack();
                // Log l'erreur $e->getMessage()
                return ['success' => false, 'errors' => ['global' => 'Une erreur serveur est survenue lors de la mise à jour des complements.'], 'data' => []];
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
            if (!filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
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
            //On vérifie si le tarif nécessite un code eet si le bon est saisi
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
                    $errors["{$field}[$detailId]"] = $message;
                }
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Étape 5: vérifie que tous les participants ont une place assise
     * Retourne tableau ['success' => true/false, 'errors' => $errors[]]
     *
     * @param ReservationDetailTemp[] $details
     * @return array
     */
    public function validateDataStep5(array $details): array
    {
        $errors = [];

        if (empty($details)) {
            return ['success' => true, 'errors' => []];
        }

        foreach ($details as $detail) {
            $detailId = $detail->getId();
            $placeId = $detail->getPlaceNumber();

            // Présence
            if ($placeId === null || $placeId === '') {
                $errors["places[$detailId]"] = 'Une place doit être sélectionnée.';
                continue;
            }

            // Normalisation chaîne -> entier
            if (is_string($placeId)) {
                $placeId = trim($placeId);
            }

            // Doit être un entier > 0
            $intVal = filter_var($placeId, FILTER_VALIDATE_INT);
            if ($intVal === false || (int)$intVal <= 0) {
                $errors["places[$detailId]"] = 'Identifiant de place invalide.';
            }

        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
    }

    /**
     * Étape 6: vérifie si les compléments choisis sont bien associés ç cet event et ne dépasse aps le quota
     * Retourne tableau ['success' => true/false, 'errors' => $errors[]]
     *
     * @param ReservationComplementTemp[] $reservationComplementTempTab
     * @param Event $event
     * @return array
     */
    public function validateDataStep6(array $reservationComplementTempTab, Event $event): array
    {
        $errors = [];

        foreach ($reservationComplementTempTab as $reservationComplementTemp) {
            // On s'assure que chaque élément est bien du type attendu.
            if (!$reservationComplementTemp instanceof ReservationComplementTemp) {
                $errors['global'] = 'Une erreur interne est survenue lors de la validation des tarifs.';
                // On arrête la validation ici, car la structure de données est incorrecte.
                return ['success' => false, 'errors' => $errors];
            }
            //On vérifie que le tarif existe bien
            $tarif = $this->tarifRepository->findById($reservationComplementTemp->getTarif());
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
                ($reservationComplementTemp->getTarifAccessCode() === null || $reservationComplementTemp->getTarifAccessCode() !== $tarif->getAccessCode()) // ...et que le code fourni est soit manquant, soit incorrect.
            ) {
                $errors['tarif_id_' . $tarif->getId()] = "Le code d'accès fourni pour le tarif '{$tarif->getName()}' est invalide ou manquant.";
                return ['success' => false, 'errors' => $errors];
            }
            //On vérifie qu'on ne dépasse pas la quantité autorisée
            $qtyMaxTicket = $tarif->getMaxTickets() ?? 999; // ==> $tarif->getMaxTickets() est NULL si pas de limite
            if ($reservationComplementTemp->getQty() > $qtyMaxTicket) {
                $errors['tarif_id_' . $tarif->getId()] = "La quantité demandée pour le tarif '{$tarif->getName()}' dépasse la quantité autorisée.";
            }
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        return ['success' => true, 'errors' => []];
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

        //On récupère l'Event pour les étapes suivantes qui en ont besoin
        $event = $this->eventRepository->findById($session['reservation']->getEvent());

        if ($step > 2) {
            $check = $this->validateDataStep2($session['reservation']);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 3) {
            $check = $this->validateDataStep3($session['reservation_details'], $event);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 4) {
            $check = $this->validateDataStep4($session['reservation_details']);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 5 && $session['reservation']->getEventObject()->getPiscine()->getNumberedSeats() === true) {

            $check = $this->validateDataStep5($session['reservation_details']);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        if ($step > 6) {

            $check = $this->validateDataStep6($session['reservation_complements'], $event);
            if (!$check['success']) {
                return ['success' => false, 'errors' => $check['errors']];
            }
        }

        return ['success' => true];
    }

    /**
     * Pour vérifier toutes les étapes
     * @param $session
     * @return bool
     */
    public function validateAllStep($session): bool
    {
        //on fait la vérification des données de toutes les étapes
        for ($i = 1; $i <= 6;$i++) {
            if (!$this->checkPreviousStep($i, $session)) {
                $this->flashMessageService->setFlashMessage('danger', 'Erreur ' . $i . ' dans le parcours, veuillez recommencer');
                return false;
            }
        }

        return true;
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

}
