<?php

namespace app\Services\Reservation;

use app\DTO\ReservationDetailItemDTO;
use app\DTO\ReservationUserDTO;
use app\Repository\Event\EventsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\TarifsRepository;
use app\Services\Logs\LogService;
use app\Services\NageuseService;
use app\Services\UploadService;
use DateMalformedStringException;
use DateTime;

class ReservationValidationService
{
    private EventsRepository $eventsRepository;
    private NageuseService $nageuseService;
    private TarifsRepository $tarifsRepository;
    private ReservationSessionService $reservationSessionService;
    private UploadService $uploadService;
    private ReservationsPlacesTempRepository $tempRepo;
    private LogService $logService;

    public function __construct()
    {
        $this->eventsRepository = new EventsRepository();
        $this->nageuseService = new NageuseService();
        $this->tarifsRepository = new TarifsRepository();
        $this->reservationSessionService = new ReservationSessionService();
        $this->uploadService = new UploadService();
        $this->tempRepo = new ReservationsPlacesTempRepository();
        $this->logService = new LogService();
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
                nom: mb_strtoupper($nom, 'UTF-8'),
                prenom: mb_convert_case(mb_strtolower($prenom, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
                email: $email,
                telephone: $telephone
            )
        ];
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

        // Préparer les données pour le traitement (tarifs).
        $tarifs = $this->tarifsRepository->findByEventId($eventId);
        $tarifsById = [];
        foreach ($tarifs as $tarif) { $tarifsById[$tarif->getId()] = $tarif; }

        // Reconstruire la liste des participants avec les nouvelles informations.
        $updatedDetails = [];
        $justifIndex = 0;

        foreach ($reservationDetails as $index => $detail) {
            $tarifId = $detail['tarif_id'];
            $tarif = $tarifsById[$tarifId] ?? null;

            // Crée un nouveau DTO avec les informations de base
            $newItem = new ReservationDetailItemDTO(
                tarif_id: $tarifId,
                nom: mb_strtoupper($noms[$index], 'UTF-8'),
                prenom: mb_convert_case(mb_strtolower($prenoms[$index], 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
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
        return "{$sessionId}_{$tarifId}_{$safeNom}_$safePrenom.$extension";
    }

}