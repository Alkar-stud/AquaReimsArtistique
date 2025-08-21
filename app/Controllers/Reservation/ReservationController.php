<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\TarifsRepository;
use app\Services\ReservationSessionService;
use app\Repository\Event\EventsRepository;

#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends AbstractController
{
    private ReservationSessionService $sessionService;
    private EventsRepository $eventsRepository;
    private ReservationsRepository $reservationsRepository;
    private TarifsRepository $tarifsRepository;

    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->sessionService = new ReservationSessionService();
        $this->eventsRepository = new EventsRepository();
        $this->reservationsRepository = new ReservationsRepository();
        $this->tarifsRepository = new TarifsRepository();
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

        $this->render('reservation/home', [
            'events' => $events,
            'groupes' => $groupes,
            'nageusesParGroupe' => $nageusesParGroupe,
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
        $_SESSION['reservation'][session_id()]['nageuse_id'] = $nageuseId;

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
        if (!$reservation || empty($reservation['event_id']) || empty($reservation['nageuse_id'])) {
            // Redirection vers la page de début de réservation avec un message
            header('Location: /reservation?session_expiree=1');
            exit;
        }

        $this->render('reservation/etape2', [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? []
        ], 'Réservations');
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
        if (!$reservation || empty($reservation['event_id']) || empty($reservation['nageuse_id'])) {
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
                            'code' => $detail['access_code_entered'] ?? ''
                        ];
                        break 2;
                    }
                }
            }
        }

        // Récupérer les quantités déjà sélectionnées
        $reservationDetails = $reservation['reservation_detail'] ?? [];
        $tarifQuantities = [];
        foreach ($reservationDetails as $detail) {
            if (isset($detail['tarif_id']) && isset($detail['qty'])) {
                $tarifQuantities[$detail['tarif_id']] = $detail['qty'];
            }
        }

        $this->render('reservation/etape3', [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? [],
            'tarifs' => $tarifs,
            'limitation' => $_SESSION['reservation'][session_id()]['limitPerSwimmer'],
            'placesDejaReservees' => $placesDejaReservees,
            'placesRestantes' => $placesRestantes,
            'tarifQuantities' => $tarifQuantities,
            'specialTarifSession' => $specialTarifSession
        ], 'Réservations');
    }


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
                    'access_code_entered' => $code,
                    'qty' => $tarif->getNbPlace()
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

        // Tarifs classiques
        foreach ($input['tarifs'] ?? [] as $t) {
            $id = (int)($t['id'] ?? 0);
            $qty = (int)($t['qty'] ?? 0);
            if ($qty > 0 && in_array($id, $tarifIds, true)) {
                $reservationDetails[] = [
                    'tarif_id' => $id,
                    'qty' => $qty
                ];
            }
        }

        // Tarif spécial
        if (!empty($input['specialTarif']['id'])) {
            $specialId = (int)$input['specialTarif']['id'];
            if (in_array($specialId, $tarifIds, true)) {
                $reservationDetails[] = [
                    'tarif_id' => $specialId,
                    'access_code_entered' => $input['specialTarif']['code'] ?? ''
                ];
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

        $this->render('reservation/etape4', [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $_SESSION['reservation'][session_id()] ?? []
        ], 'Réservations');
    }

}