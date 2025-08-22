<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Event\EventInscriptionDatesRepository;
use app\Repository\TarifsRepository;
use app\Services\ReservationSessionService;
use app\Repository\Event\EventsRepository;
use app\Utils\ReservationContextHelper;


#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends AbstractController
{
    private ReservationSessionService $sessionService;
    private EventsRepository $eventsRepository;
    private ReservationsRepository $reservationsRepository;
    private TarifsRepository $tarifsRepository;
    private EventInscriptionDatesRepository $eventInscriptionDatesRepository;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->sessionService = new ReservationSessionService();
        $this->eventsRepository = new EventsRepository();
        $this->reservationsRepository = new ReservationsRepository();
        $this->tarifsRepository = new TarifsRepository();
        $this->eventInscriptionDatesRepository = new EventInscriptionDatesRepository();
    }

    // Page d'accueil du processus de réservation
    public function index(): void
    {
        $this->sessionService->clearSession();
        $events = $this->eventsRepository->findUpcoming();

        // Récupérer tous les groupes
        $groupesRepository = new GroupesNageusesRepository();
        $groupes = $groupesRepository->findAll();

        // Récupérer les nageuses par groupe
        $nageusesRepository = new NageusesRepository();
        $nageuses = $nageusesRepository->findAll();
        $nageusesParGroupe = [];
        foreach ($nageuses as $nageuse) {
            $groupeId = $nageuse->getGroupe();
            if (!isset($nageusesParGroupe[$groupeId])) {
                $nageusesParGroupe[$groupeId] = [];
            }
            $nageusesParGroupe[$groupeId][] = [
                'id' => $nageuse->getId(),
                'nom' => $nageuse->getName()
            ];
        }

        //Récupération des périodes d'ouverture des inscriptions
        $inscriptionsParEvent = [];
        foreach ($events as $event) {
            $inscriptionsParEvent[$event->getId()] = $this->eventInscriptionDatesRepository->findByEventId($event->getId());
        }

        //Récupération de l'éventuelle session en cours
        $reservation = $_SESSION['reservation'][session_id()] ?? [];
        $selectedSession = $reservation['event_session_id'] ?? null;
        $selectedNageuse = $reservation['nageuse_id'] ?? null;
        $selectedGroupe = null;

        if ($selectedNageuse) {
            foreach ($nageuses as $nageuse) {
                if ($nageuse->getId() == $selectedNageuse) {
                    $selectedGroupe = $nageuse->getGroupe();
                    break;
                }
            }
        }

        //Récupération des périodes d'ouverture des inscriptions
        $periodesOuvertes = [];
        $nextPublicOuvertures = [];
        foreach ($events as $event) {
            $periodes = $inscriptionsParEvent[$event->getId()] ?? [];
            $now = new \DateTime();
            $periodeOuverte = null;
            $nextPublic = null;
            foreach ($periodes as $periode) {
                if ($now >= $periode->getStartRegistrationAt() && $now <= $periode->getCloseRegistrationAt()) {
                    $periodeOuverte = $periode;
                }
                if ($periode->getAccessCode() === null && $periode->getStartRegistrationAt() > $now) {
                    if ($nextPublic === null || $periode->getStartRegistrationAt() < $nextPublic->getStartRegistrationAt()) {
                        $nextPublic = $periode;
                    }
                }
            }
            $periodesOuvertes[$event->getId()] = $periodeOuverte;
            $nextPublicOuvertures[$event->getId()] = $nextPublic;
        }

        $this->render('reservation/home', [
            'events' => $events,
            'groupes' => $groupes,
            'nageusesParGroupe' => $nageusesParGroupe,
            'inscriptionsParEvent' => $inscriptionsParEvent,
            'periodesOuvertes' => $periodesOuvertes,
            'nextPublicOuvertures' => $nextPublicOuvertures,
            'csrf_token' => $this->getCsrfToken(),
            'selectedSession' => $selectedSession,
            'selectedNageuse' => $selectedNageuse,
            'selectedGroupe' => $selectedGroupe
        ], 'Réservations');
    }

    #[Route('/reservation/check-nageuse-limit', name: 'check_nageuse_limit', methods: ['GET'])]
    public function checkNageuseLimit(): void
    {
        $csrfToken = $_GET['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'check-nageuse-limit', false)) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide : ' . $csrfToken]);
            return;
        }

        $eventId = (int)($_GET['event_id'] ?? 0);
        $nageuseId = (int)($_GET['nageuse_id'] ?? 0);

        if (!$eventId || !$nageuseId) {
            $this->json(['success' => true, 'limiteAtteinte' => true, 'error' => 'Paramètres manquants']);
            return;
        }

        $event = $this->eventsRepository->findById($eventId);
        //S'il n'y a pas de limite pour cet event.
        if (!$event || $event->getLimitationPerSwimmer() === null) {
            $this->json(['success' => true, 'limiteAtteinte' => false]);
            return;
        }

        $limite = $event->getLimitationPerSwimmer();
        //On enregistre la limite dans $_SESSION
        $_SESSION['reservation'][session_id()]['limitPerSwimmer'] = $limite;

        $count = $this->reservationsRepository->countActiveReservationsForEvent($eventId, $nageuseId);

        $this->json(['success' => true, 'limiteAtteinte' => $count >= $limite]);
    }

    /*
     * Pour valider et enregistrer en $_SESSION les valeurs de l'étape 1
     *
     */
    #[Route('/reservation/etape1', methods: ['POST'])]
    public function etape1(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'reservation_etape1')) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide : ' . $csrfToken]);
            return;
        }

        $eventId = (int)($input['event_id'] ?? 0);
        $sessionId = (int)($input['event_session_id'] ?? 0);
        $nageuseId = isset($input['nageuse_id']) ? (int)$input['nageuse_id'] : null;

        // Contrôles
        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            $this->json(['success' => false, 'error' => 'Événement invalide.']);
            return;
        }

        $sessions = $event->getSessions();
        $sessionIds = array_map(fn($s) => $s->getId(), $sessions);
        if (!in_array($sessionId, $sessionIds, true)) {
            $this->json(['success' => false, 'error' => 'Séance invalide.']);
            return;
        }

        if ($event->getLimitationPerSwimmer() !== null) {
            $nageusesRepository = new \app\Repository\Nageuse\NageusesRepository();
            $nageuse = $nageusesRepository->findById($nageuseId);
            if (!$nageuse) {
                $this->json(['success' => false, 'error' => 'Nageuse invalide.']);
                return;
            }
            // Vérifier la limite de spectateurs
            $limite = $event->getLimitationPerSwimmer();
            $count = $this->reservationsRepository->countActiveReservationsForEvent($eventId, $nageuseId);

            if ($count >= $limite) {
                $this->json(['success' => false, 'error' => 'Le quota de spectateurs pour cette nageuse est atteint.']);
                return;
            }
        }

        $_SESSION['reservation'][session_id()]['event_id'] = $eventId;
        $_SESSION['reservation'][session_id()]['event_session_id'] = $sessionId;
        // N'ajouter la nageuse que si elle est requise
        if ($event->getLimitationPerSwimmer() !== null) {
            $_SESSION['reservation'][session_id()]['nageuse_id'] = $nageuseId;
        }

        $this->json(['success' => true]);
    }

    #[Route('/reservation/check-email', methods: ['POST'])]
    public function checkEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'check_email', false)) {
            $this->json(['exists' => false, 'error' => 'Token CSRF invalide']);
            return;
        }
        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        // Recherche des réservations existantes
        $reservations = $this->reservationsRepository->findByEmailAndEvent($email, $eventId);
        if ($reservations) {
            $result = [];
            foreach ($reservations as $r) {
                $result[] = [
                    'nb_places' => $r->getNbPlaces(),
                    'session_date' => $r->getSession()->getEventStartAt()->format('d/m/Y H:i')
                ];
            }
            $this->json(['exists' => true, 'reservations' => $result]);
        } else {
            $this->json(['exists' => false]);
        }
    }

    #[Route('/reservation/etape2Display', methods: ['GET'])]
    public function etape2Display(): void
    {
        $reservation = $_SESSION['reservation'][session_id()] ?? null;
        $event = null;
        if (
            !$reservation
            || empty($reservation['event_id'])
            || ($event && $event->getLimitationPerSwimmer() !== null && empty($reservation['nageuse_id']))
        ) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        if ($reservation && !empty($reservation['nageuse_id'])) {
            $nageusesRepository = new \app\Repository\Nageuse\NageusesRepository();
            $nageuse = $nageusesRepository->findById($reservation['nageuse_id']);
        }

        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/etape2', array_merge($context, [
            'csrf_token' => $this->getCsrfToken()
        ]), 'Réservations');
    }

    #[Route('/reservation/etape2', methods: ['POST'])]
    public function etape2(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'reservation_etape2')) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }

        $nom = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $email = trim($input['email'] ?? '');
        $telephone = trim($input['telephone'] ?? '');

        // Validation simple
        if ($nom === '' || $prenom === '' || $email === '' || $telephone === '') {
            $this->json(['success' => false, 'error' => 'Tous les champs sont obligatoires.']);
            return;
        }
        if (strtolower($nom) === strtolower($prenom)) {
            $this->json(['success' => false, 'error' => 'Le nom et le prénom ne doivent pas être identiques.']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'Adresse mail invalide.']);
            return;
        }
        if (!preg_match('/^0[1-9](\d{8})$/', str_replace(' ', '', $telephone))) {
            $this->json(['success' => false, 'error' => 'Numéro de téléphone invalide.']);
            return;
        }

        // Enregistrement en session
        $_SESSION['reservation'][session_id()]['user'] = [
            'nom' => strtoupper($nom),
            'prenom' => ucwords($prenom),
            'email' => $email,
            'telephone' => $telephone
        ];

        $this->json(['success' => true]);
    }

    #[Route('/reservation/etape3Display', methods: ['GET'])]
    public function etape3Display(): void
    {
        $reservation = $_SESSION['reservation'][session_id()] ?? null;
        $event = null;
        if ($reservation && !empty($reservation['event_id'])) {
            $event = $this->eventsRepository->findById($reservation['event_id']);
        }
        if (
            !$reservation
            || empty($reservation['event_id'])
            || ($event && $event->getLimitationPerSwimmer() !== null && empty($reservation['nageuse_id']))
        ) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        $tarifs = $this->tarifsRepository->findByEventId($_SESSION['reservation'][session_id()]['event_id']);
        $limitation = $this->reservationsRepository->countReservationsForNageuse($_SESSION['reservation'][session_id()]['event_id'], $_SESSION['reservation'][session_id()]['nageuse_id']);
        $placesDejaReservees = $reservation['places_deja_reservees'] ?? 0;
        $placesRestantes = $limitation !== null ? max(0, $limitation - $placesDejaReservees) : null;

        // Pour chercher un tarif spécial déjà enregistré
        $reservationDetails = $reservation['reservation_detail'] ?? [];
        $specialTarifSession = null;
        foreach ($reservationDetails as $detail) {
            if (isset($detail['tarif_id'])) {
                foreach ($tarifs as $tarif) {
                    if ($tarif->getId() == $detail['tarif_id'] && $tarif->getAccessCode()) {
                        $specialTarifSession = [
                            'id' => $tarif->getId(),
                            'libelle' => $tarif->getLibelle(),
                            'description' => $tarif->getDescription(),
                            'nb_place' => $tarif->getNbPlace(),
                            'price' => $tarif->getPrice(),
                            'code' => $detail['access_code'] ?? ''
                        ];
                        break 2;
                    }
                }
            }
        }

        // Récupérer les quantités déjà sélectionnées, enregistrer le tableau pour la vue
        $reservationDetails = $reservation['reservation_detail'] ?? [];
        $tarifQuantities = [];
        foreach ($reservationDetails as $detail) {
            if (isset($detail['tarif_id'])) {
                $tid = $detail['tarif_id'];
                if (!isset($tarifQuantities[$tid])) {
                    $tarifQuantities[$tid] = 0;
                }
                $tarifQuantities[$tid]++;
            }
        }

        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/etape3', array_merge($context, [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? [],
            'tarifs' => $tarifs,
            'limitation' => $_SESSION['reservation'][session_id()]['limitPerSwimmer'],
            'placesDejaReservees' => $placesDejaReservees,
            'placesRestantes' => $placesRestantes,
            'tarifQuantities' => $tarifQuantities,
            'specialTarifSession' => $specialTarifSession
        ]), 'Réservations');
    }


    //Pour valider les tarifs avec code
    #[Route('/reservation/validate-special-code', methods: ['POST'])]
    public function validateSpecialCode(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'validate_special_code', false)) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }
        $eventId = (int)($input['event_id'] ?? 0);
        $code = trim($input['code'] ?? '');

        if (!$eventId || !$code) {
            $this->json(['success' => false, 'error' => 'Paramètres manquants']);
            return;
        }

        $tarifs = $this->tarifsRepository->findByEventId($eventId);
        foreach ($tarifs as $tarif) {
            if ($tarif->getAccessCode() && strcasecmp($tarif->getAccessCode(), $code) === 0) {
                //On enregistre la place dans $_SESSION pour éviter de devoir resaisir le code en cas de retour arrière
                $_SESSION['reservation'][session_id()]['reservation_detail'][] = [
                    'tarif_id' => $tarif->getId(),
                    'access_code' => $code
                ];
                $this->json([
                    'success' => true,
                    'tarif' => [
                        'id' => $tarif->getId(),
                        'libelle' => $tarif->getLibelle(),
                        'description' => $tarif->getDescription(),
                        'nb_place' => $tarif->getNbPlace(),
                        'price' => $tarif->getPrice()
                    ]
                ]);
                return;
            }
        }
        $this->json(['success' => false, 'error' => 'Code invalide ou non reconnu.']);
    }

    #[Route('/reservation/remove-special-tarif', methods: ['POST'])]
    public function removeSpecialTarif(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'remove_special_tarif', false)) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }
        $tarifId = (int)($input['tarif_id'] ?? 0);
        if (!$tarifId) {
            $this->json(['success' => false, 'error' => 'Paramètre manquant']);
            return;
        }
        $session = &$_SESSION['reservation'][session_id()]['reservation_detail'];
        if (is_array($session)) {
            $_SESSION['reservation'][session_id()]['reservation_detail'] = array_values(
                array_filter($session, fn($d) => ($d['tarif_id'] ?? null) != $tarifId)
            );
        }
        $this->json(['success' => true]);
    }


    #[Route('/reservation/validate-access-code', methods: ['POST'])]
    public function validateAccessCode(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'validate_access_code', false)) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }
        $eventId = (int)($input['event_id'] ?? 0);
        $code = trim($input['code'] ?? '');

        if (!$eventId || !$code) {
            $this->json(['success' => false, 'error' => 'Paramètres manquants']);
            return;
        }

        $periodes = $this->eventInscriptionDatesRepository->findByEventId($eventId);
        $now = new \DateTime();
        $codeTrouve = false;
        foreach ($periodes as $periode) {
            if ($periode->getAccessCode() && strcmp($periode->getAccessCode(), $code) === 0) {
                $codeTrouve = true;
                if ($now < $periode->getStartRegistrationAt()) {
                    $dateLocale = $periode->getStartRegistrationAt()->format('d/m/Y H:i');
                    $this->json([
                        'success' => false,
                        'error' => "Ce code est valide, mais la période d'inscription n'a pas encore commencé. Ouverture le $dateLocale."
                    ]);
                    return;
                }
                if ($now > $periode->getCloseRegistrationAt()) {
                    $this->json([
                        'success' => false,
                        'error' => "Ce code est valide, mais la période d'inscription est terminée."
                    ]);
                    return;
                }
                // Code valide et période ouverte
                $_SESSION['reservation'][session_id()]['access_code_valid'][$eventId] = true;
                $this->json(['success' => true]);
                return;
            }
        }
        if (!$codeTrouve) {
            $this->json(['success' => false, 'error' => 'Code inconnu pour cet événement.']);
        }
    }

    #[Route('/reservation/etape3', methods: ['POST'])]
    public function etape3(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'reservation_etape3')) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }

        $eventId = $_SESSION['reservation'][session_id()]['event_id'] ?? null;
        if (!$eventId) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $tarifs = $this->tarifsRepository->findByEventId($eventId);
        $tarifIds = array_map(fn($t) => $t->getId(), $tarifs);

        $reservationDetails = [];

        // récupérer l'ancien détail pour conserver nom/prénom si on revient de l'étape suivante pour ajouter un tarif par exemple
        $oldDetails = $_SESSION['reservation'][session_id()]['reservation_detail'] ?? [];
        // On crée un index par tarif_id pour retrouver les anciens noms/prénoms
        $oldByTarif = [];
        foreach ($oldDetails as $detail) {
            $tid = $detail['tarif_id'];
            if (!isset($oldByTarif[$tid])) $oldByTarif[$tid] = [];
            $oldByTarif[$tid][] = $detail;
        }

        // Tarifs classiques
        foreach ($input['tarifs'] ?? [] as $t) {
            $id = (int)($t['id'] ?? 0);
            $qty = (int)($t['qty'] ?? 0);
            if ($qty > 0 && in_array($id, $tarifIds, true)) {
                for ($i = 0; $i < $qty; $i++) {
                    $detail = ['tarif_id' => $id];
                    // Si on a un ancien nom/prénom pour ce tarif à cette position, on le recopie
                    if (!empty($oldByTarif[$id][$i])) {
                        if (isset($oldByTarif[$id][$i]['nom'])) $detail['nom'] = $oldByTarif[$id][$i]['nom'];
                        if (isset($oldByTarif[$id][$i]['prenom'])) $detail['prenom'] = $oldByTarif[$id][$i]['prenom'];
                    }
                    $reservationDetails[] = $detail;
                }
            }
        }

        // Tarif spécial
        if (!empty($input['specialTarif']['id'])) {
            $specialId = (int)$input['specialTarif']['id'];
            if (in_array($specialId, $tarifIds, true)) {
                $specialTarifObj = null;
                foreach ($tarifs as $t) {
                    if ($t->getId() == $specialId) {
                        $specialTarifObj = $t;
                        break;
                    }
                }
                $qty = $specialTarifObj ? (int)$specialTarifObj->getNbPlace() : 1;
                for ($i = 0; $i < $qty; $i++) {
                    $detail = [
                        'tarif_id' => $specialId,
                        'access_code' => $input['specialTarif']['code'] ?? ''
                    ];
                    if (!empty($oldByTarif[$specialId][$i])) {
                        if (isset($oldByTarif[$specialId][$i]['nom'])) $detail['nom'] = $oldByTarif[$specialId][$i]['nom'];
                        if (isset($oldByTarif[$specialId][$i]['prenom'])) $detail['prenom'] = $oldByTarif[$specialId][$i]['prenom'];
                    }
                    $reservationDetails[] = $detail;
                }
            }
        }

        if (empty($reservationDetails)) {
            $this->json(['success' => false, 'error' => 'Aucun tarif sélectionné.']);
            return;
        }

        // Enregistrement en session
        $_SESSION['reservation'][session_id()]['reservation_detail'] = $reservationDetails;

        $this->json(['success' => true]);
    }


    #[Route('/reservation/etape4Display', methods: ['GET'])]
    public function etape4Display(): void
    {
        $reservation = $_SESSION['reservation'][session_id()] ?? null;
        if (!$reservation || empty($reservation['event_id']) || empty($reservation['nageuse_id'])) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $event = $this->eventsRepository->findById($reservation['event_id']);

        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/etape4', array_merge($context, [
            'tarifs' => $tarifs,
            'numberedSeats' => $event->getPiscine()->getNumberedSeats(),
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? []
        ]), 'Réservations');
    }

    #[Route('/reservation/etape4', methods: ['POST'])]
    public function etape4(): void
    {
        //On fait différemment car il y a peut-être un fichier
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'reservation_etape4')) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }

        // Extensions et types MIME autorisés
        $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $allowedMimeTypes = [
            'application/pdf',
            'image/jpeg',
            'image/png'
        ];

        //Récupération des données du formaulaire
        $noms = $_POST['noms'] ?? [];
        $prenoms = $_POST['prenoms'] ?? [];
        $justificatifs = $_FILES['justificatifs'] ?? null;
        $justifIndex = 0;

        if (count($noms) !== count($prenoms)) {
            $this->json(['success' => false, 'error' => 'Nombre de noms/prénoms incohérent.']);
            return;
        }

        $couples = [];
        for ($i = 0; $i < count($noms); $i++) {
            $nom = trim($noms[$i]);
            $prenom = trim($prenoms[$i]);
            if ($nom === '' || $prenom === '') {
                $this->json(['success' => false, 'error' => "Nom ou prénom manquant pour le participant " . ($i+1)]);
                return;
            }
            if (strtolower($nom) === strtolower($prenom)) {
                $this->json(['success' => false, 'error' => "Le nom et le prénom du participant " . ($i+1) . " doivent être différents."]);
                return;
            }
            $key = strtolower($nom . '|' . $prenom);
            if (in_array($key, $couples, true)) {
                $this->json(['success' => false, 'error' => "Le couple nom/prénom du participant " . ($i+1) . " est déjà utilisé."]);
                return;
            }
            $couples[] = $key;
        }

        // Enregistrement dans chaque reservation_detail
        $reservation = &$_SESSION['reservation'][session_id()];
        if (!isset($reservation['reservation_detail']) || count($reservation['reservation_detail']) !== count($noms)) {
            $this->json(['success' => false, 'error' => 'Incohérence du nombre de participants.']);
            return;
        }
        foreach ($reservation['reservation_detail'] as $i => &$detail) {
            $detail['nom'] = strtoupper($noms[$i]);
            $detail['prenom'] = ucwords($prenoms[$i]);
            // Récupérer le tarif correspondant
            $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
            $tarif = null;
            foreach ($tarifs as $t) {
                if ($t->getId() == $detail['tarif_id']) {
                    $tarif = $t;
                    break;
                }
            }
            // Si justificatif requis
            if ($tarif && $tarif->getIsProofRequired()) {
                if (
                    isset($justificatifs['name'][$justifIndex]) &&
                    $justificatifs['error'][$justifIndex] === UPLOAD_ERR_OK
                ) {
                    if ($justificatifs['size'][$justifIndex] > MAX_UPLOAD_PROOF_SIZE * 1024 * 1024) {
                        $this->json(['success' => false, 'error' => "Le justificatif du participant " . ($i+1) . " dépasse la taille maximale autorisée (2 Mo)."]);
                        return;
                    }
                    $originalName = $justificatifs['name'][$justifIndex];
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $mimeType = mime_content_type($justificatifs['tmp_name'][$justifIndex]);

                    if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
                        $this->json(['success' => false, 'error' => "Format de justificatif non autorisé (PDF, JPG, PNG uniquement)."]);
                        return;
                    }
                    $sessionId = session_id();
                    $tarifId = $detail['tarif_id'];
                    $nom = strtolower(preg_replace('/[^a-z0-9]/i', '', $noms[$i]));
                    $prenom = strtolower(preg_replace('/[^a-z0-9]/i', '', $prenoms[$i]));
                    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                    $uniqueName = "{$sessionId}_{$tarifId}_{$nom}_{$prenom}.{$extension}";
                    $uploadPath = __DIR__ . '/../../..' . UPLOAD_PROOF_PATH . 'temp/' . $uniqueName;
                    move_uploaded_file($justificatifs['tmp_name'][$justifIndex], $uploadPath);
                    $detail['justificatif_name'] = $uniqueName;
                    move_uploaded_file($justificatifs['tmp_name'][$justifIndex], __DIR__ . '/../../..' . UPLOAD_PROOF_PATH . 'temp/' . $uniqueName);
                    $detail['justificatif_name'] = $uniqueName;
                    unset($reservation['reservation_detail'][$justifIndex]['justificatif_name']);
                    $justifIndex++;
                } elseif (!empty($detail['justificatif_name'])) {
                    // Déjà en session
                    $justifIndex++;
                } else {
                    $this->json(['success' => false, 'error' => "Justificatif manquant pour le participant: " . ($i+1)]);
                    return;
                }
            }
        }

        $this->json(['success' => true]);
    }

    #[Route('/reservation/etape5Display', methods: ['GET'])]
    public function etape5Display(): void
    {
        $reservation = $_SESSION['reservation'][session_id()] ?? null;
        if (!$reservation || empty($reservation['event_id']) || empty($reservation['nageuse_id'])) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }


        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/etape5', array_merge($context, [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? []
        ]), 'Réservations');
    }

    #[Route('/reservation/etape5', methods: ['POST'])]
    public function etape5(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'reservation_etape4')) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide']);
            return;
        }

    }


    #[Route('/reservation/etape6Display', methods: ['GET'])]
    public function etape6Display(): void
    {
        $reservation = $_SESSION['reservation'][session_id()] ?? null;
        if (!$reservation || empty($reservation['event_id']) || empty($reservation['nageuse_id'])) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }


        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/etape6', array_merge($context, [
             'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? []
        ]), 'Réservations');
    }





}