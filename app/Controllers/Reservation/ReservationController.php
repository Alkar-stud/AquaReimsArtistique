<?php
namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Reservation\ReservationMailsSent;
use app\Repository\MailTemplateRepository;
use app\Repository\Reservation\ReservationMailsSentRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Event\EventInscriptionDatesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\TarifsRepository;
use app\Services\ReservationSessionService;
use app\Repository\Event\EventsRepository;
use app\Utils\ReservationContextHelper;
use DateTime;


#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends AbstractController
{
    private ReservationSessionService $sessionService;
    private EventsRepository $eventsRepository;
    private ReservationsRepository $reservationsRepository;
    private TarifsRepository $tarifsRepository;
    private EventInscriptionDatesRepository $eventInscriptionDatesRepository;
    private PiscineGradinsZonesRepository $zonesRepository;
    private PiscineGradinsPlacesRepository $placesRepository;
    private ReservationMailsSentRepository $reservationMailsSentRepository;
    private MailTemplateRepository $mailTemplateRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private ReservationsPlacesTempRepository $tempRepo;


    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->sessionService = new ReservationSessionService();
        $this->eventsRepository = new EventsRepository();
        $this->reservationsRepository = new ReservationsRepository();
        $this->tarifsRepository = new TarifsRepository();
        $this->eventInscriptionDatesRepository = new EventInscriptionDatesRepository();
        $this->zonesRepository = new PiscineGradinsZonesRepository();
        $this->placesRepository = new PiscineGradinsPlacesRepository();
        $this->reservationMailsSentRepository = new ReservationMailsSentRepository();
        $this->mailTemplateRepository = new MailTemplateRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->tempRepo = new ReservationsPlacesTempRepository();

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
            $now = new DateTime();
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

        $this->render('reservation/etape1', [
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

        $count = $this->reservationsRepository->countActiveReservationsForEvent($eventId);

        $this->json(['success' => true, 'limiteAtteinte' => $count >= $limite]);
    }

    /*
     * Pour valider et enregistrer en $_SESSION les valeurs de l'étape 1
     *
     */
    #[Route('/reservation/etape1', name: 'etape1', methods: ['POST'])]
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
            $nageusesRepository = new NageusesRepository();
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

    /*
     * Pour vérifier si un email a déjà été utilisé pour une réservation
     */
    #[Route('/reservation/check-email', name: 'check-email', methods: ['POST'])]
    public function checkEmail(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'check_email', false)) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide', 'csrf_token' => $this->getCsrfToken()]);
            return;
        }

        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        // Fetch event and tarifs, needed for place counting and session details
        $event = $this->eventsRepository->findById($eventId);
        if (!$event) {
            $this->json(['exists' => false, 'error' => 'Événement invalide.']);
            return;
        }
        $tarifs = $this->tarifsRepository->findByEventId($eventId);

        // Recherche des réservations existantes
        $reservations = $this->reservationsRepository->findByEmailAndEvent($email, $eventId);
        if ($reservations) {
            $totalPlacesReserved = 0;
            $reservationSummaries = [];
            foreach ($reservations as $r) {
                // Récupérer l'objet de la session pour cette réservation
                $sessionObj = null;
                foreach ($event->getSessions() as $s) {
                    if ($s->getId() == $r->getEventSession()) {
                        $sessionObj = $s;
                        break;
                    }
                }

                // Récupérer le détail de cette réservation
                $details = $this->reservationsDetailsRepository->findByReservation($r->getId());
                $nbPlacesForThisReservation = $this->countPlacesAssises($details, $tarifs);

                $totalPlacesReserved += $nbPlacesForThisReservation;

                $reservationSummaries[] = [
                    'reservation_id' => $r->getId(), // Add reservation ID for potential re-sending email
                    'nb_places' => $nbPlacesForThisReservation,
                    'session_date' => $sessionObj ? $sessionObj->getEventStartAt()->format('d/m/Y H:i') : 'N/A'
                ];
            }
            $this->json([
                'exists' => true,
                'csrf_token' => $this->getCsrfToken(),
                'total_places_reserved' => $totalPlacesReserved,
                'num_reservations' => count($reservations),
                'reservation_summaries' => $reservationSummaries
            ]);
        } else {
            $this->json(['exists' => false]);
        }
    }

    #[Route('/reservation/resend-confirmation', name: 'app_reservation_resend_confirmation', methods: ['POST'])]
    public function resendConfirmation(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $csrfToken = $input['csrf_token'] ?? '';
        if (!$this->validateCsrfAndLog($csrfToken, 'resend_confirmation', false)) {
            $this->json(['success' => false, 'error' => 'Token CSRF invalide', 'csrf_token' => $this->getCsrfToken()]);
            return;
        }

        $email = trim($input['email'] ?? '');
        $eventId = (int)($input['event_id'] ?? 0);

        if (empty($email) || empty($eventId)) {
            $this->json(['success' => false, 'error' => 'Paramètres manquants.']);
            return;
        }

        $reservations = $this->reservationsRepository->findByEmailAndEvent($email, $eventId);
        if (empty($reservations)) {
            $this->json(['success' => false, 'error' => 'Aucune réservation trouvée pour cet email et cet événement.']);
            return;
        }

        $mailService = new \app\Services\MailService();
        $template = $this->mailTemplateRepository->findByCode('paiement_confirme');
        if (!$template) {
            $this->json(['success' => false, 'error' => 'Template de mail introuvable.']);
            return;
        }

        $sentCount = 0;
        $limitReachedCount = 0;

        foreach ($reservations as $reservation) {
            // Vérifier la limite d'envoi en utilisant une méthode dédiée du repository
            $confirmationSentCount = $this->reservationMailsSentRepository->countSentMails($reservation->getId(), $template->getId());

            if ($confirmationSentCount >= 2) { // Original + 1 renvoi
                $limitReachedCount++;
                continue; // Limite atteinte, on passe au suivant
            }

            // Hydrater l'objet event pour le service mail
            $event = $this->eventsRepository->findById($reservation->getEvent());
            $reservation->setEventObject($event);

            if ($mailService->sendReservationConfirmationEmail($reservation)) {
                $mailSentRecord = new ReservationMailsSent();
                $mailSentRecord->setReservation($reservation->getId())->setMailTemplate($template->getId())->setSentAt(date('Y-m-d H:i:s'));
                $this->reservationMailsSentRepository->insert($mailSentRecord);
                $sentCount++;
            }
        }

        $this->json(['success' => true, 'message' => "$sentCount mail(s) de confirmation renvoyé(s). $limitReachedCount réservation(s) avaient déjà atteint la limite de renvoi."]);
    }



    #[Route('/reservation/etape2Display', name: 'etape2Display', methods: ['GET'])]
    public function etape2Display(): void
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

        if ($reservation && !empty($reservation['nageuse_id'])) {
            $nageusesRepository = new NageusesRepository();
            $nageuse = $nageusesRepository->findById($reservation['nageuse_id']);
        }

        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/etape2', array_merge($context, [
            'csrf_token' => $this->getCsrfToken()
        ]), 'Réservations');
    }

    #[Route('/reservation/etape2', name: 'etape2', methods: ['POST'])]
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

    #[Route('/reservation/etape3Display', name: 'etape3Display', methods: ['GET'])]
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

        //S'il y a limitation des places par nageuse
        if ($event->getLimitationPerSwimmer() !== null) {
            // Limite maximale autorisée par nageuse
            $limiteMaxParNageuse = $event->getLimitationPerSwimmer();

            $placesDejaReservees = $this->reservationsRepository->countReservationsForNageuse(
                $_SESSION['reservation'][session_id()]['event_id'],
                $_SESSION['reservation'][session_id()]['nageuse_id']
            );
            // Calculer les places encore disponibles
            $placesRestantes = max(0, $limiteMaxParNageuse - $placesDejaReservees);
        } else {
            $_SESSION['reservation'][session_id()]['limitPerSwimmer'] = null;
            $placesDejaReservees = null;
            $placesRestantes = null;
        }

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
    #[Route('/reservation/validate-special-code', name: 'validate_special_code', methods: ['POST'])]
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

    #[Route('/reservation/remove-special-tarif', name: 'remove_special_tarif', methods: ['POST'])]
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


    #[Route('/reservation/validate-access-code', name: 'validate_access_code', methods: ['POST'])]
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
        $now = new DateTime();
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

    #[Route('/reservation/etape3', name: 'etape3', methods: ['POST'])]
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


    #[Route('/reservation/etape4Display', name: 'etape4Display', methods: ['GET'])]
    public function etape4Display(): void
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

    #[Route('/reservation/etape4', name: 'etape4',methods: ['POST'])]
    public function etape4(): void
    {
        //On fait différemment, car il y a peut-être un fichier
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

        //Récupération des données du formulaire
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
                    $uniqueName = "{$sessionId}_{$tarifId}_{$nom}_$prenom.$extension";
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

        $event = $this->eventsRepository->findById($reservation['event_id']);
        $this->json(['success' => true, 'numberedSeats' => $event->getPiscine()->getNumberedSeats()]);
    }

    #[Route('/reservation/etape5Display', name: 'etape5Display', methods: ['GET'])]
    public function etape5Display(): void
    {
        $sessionId = session_id();
        //Récupère les éléments de la réservation en cours dans la session $_SESSION
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
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

        //Suppression des réservations en cours dont le timeout est expiré
        $this->tempRepo->deleteExpired((new \DateTime())->format('Y-m-d H:i:s'));
        // Récupérer les réservations temporaires de toutes les sessions restantes
        $tempAllSeats = $this->tempRepo->findAll();
        // Remet à null seat_id et seat_name pour chaque participant directement dans $_SESSION
        if (isset($_SESSION['reservation'][$sessionId]['reservation_detail']) && is_array($_SESSION['reservation'][$sessionId]['reservation_detail'])) {
            foreach ($_SESSION['reservation'][$sessionId]['reservation_detail'] as &$detail) {
                $detail['seat_id'] = null;
                $detail['seat_name'] = null;
            }
            unset($detail);
        }

        // Filtrage des places concernées par la session en cours
        // Récupérer les places déjà réservées de manière définitive pour cette session
        $placesReservees = $this->reservationsDetailsRepository->findReservedSeatsForSession(
            $reservation['event_id'],
            $reservation['event_session_id']
        );

        // Construire un tableau place_id → session pour le JS de la vue
        $placesSessions = [];
        foreach ($tempAllSeats as $t) {
            $placesSessions[$t->getPlaceId()] = $t->getSession();
            //Si getSession() correspond à la session courante, on met à jour $_SESSION seat_id et seat_name
            if ($t->getSession() === $sessionId) {
                $index = $t->getIndex();
                $placeId = $t->getPlaceId();
                $place = $this->placesRepository->findById($placeId);
                $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_id'] = $placeId;
                $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_name'] = $place ? $place->getFullPlaceName() : $placeId;
            }
        }

        //Envoyer l'événement
        $event = $this->eventsRepository->findById($reservation['event_id']);
        $numberedSeats = $event->getPiscine()->getNumberedSeats();

        //Envoyer les tarifs
        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $nbPlacesAssises = 0;
        foreach ($reservation['reservation_detail'] ?? [] as $detail) {
            foreach ($tarifs as $tarif) {
                if ($tarif->getId() == $detail['tarif_id'] && $tarif->getNbPlace() !== null) {
                    $nbPlacesAssises++;
                }
            }
        }

        //Pour envoyer le nom des places au lieu de seulement leur ID
        $piscineId = $event->getPiscine()->getId();
        $zones = $this->zonesRepository->findOpenZonesByPiscine($piscineId);

        $zonesWithPlaces = [];
        foreach ($zones as $zone) {
            $zonesWithPlaces[] = [
                'zone' => $zone,
                'places' => $this->placesRepository->findByZone($zone->getId())
            ];
        }

        //Pour afficher le contexte récapitulatif
        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $reservation);
        $this->render('reservation/etape5', array_merge($context, [
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $reservation,
            'numberedSeats' => $numberedSeats,
            'nbPlacesAssises' => $nbPlacesAssises,
            'zonesWithPlaces' => $zonesWithPlaces,
            'placesReservees' => $placesReservees,
            'placesSessions' => $placesSessions
        ]), 'Réservations');
    }

    #[Route('/reservation/etape5', name: 'etape5', methods: ['POST'])]
    public function etape5(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$this->checkCsrfOrJsonError($input['csrf_token'] ?? '', 'reservation_etape5')) return;

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $event = $this->eventsRepository->findById($reservation['event_id']);
        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $seats = $input['seats'] ?? [];

        $nbPlacesAssises = $this->countPlacesAssises($reservation['reservation_detail'] ?? [], $tarifs);

        if (count($seats) !== $nbPlacesAssises) {
            $this->json(['success' => false, 'error' => 'Nombre de places sélectionnées incorrect.']);
            return;
        }

        // Vérifier que chaque place est bien réservée temporairement pour cette session
        $tempSeats = $this->tempRepo->findAllSeatsBySession($sessionId) ?? [];
        $tempSeatIds = array_map(fn($t) => $t->getPlaceId(), $tempSeats);

        foreach ($seats as $seatId) {
            if (!in_array($seatId, $tempSeatIds)) {
                $this->json(['success' => false, 'error' => "La place $seatId n'est pas réservée pour cette session."]);
                return;
            }
        }

        // Mise à jour des détails de réservation
        foreach ($_SESSION['reservation'][$sessionId]['reservation_detail'] as $i => &$detail) {
            $seatId = $seats[$i] ?? null;
            $detail['seat_id'] = $seatId;
            if ($seatId) {
                $place = $this->placesRepository->findById($seatId);
                $detail['seat_name'] = $place ? $place->getFullPlaceName() : $seatId;
            } else {
                $detail['seat_name'] = null;
            }
        }
        unset($detail);

        $_SESSION['reservation'][$sessionId]['selected_seats'] = $seats;

        $this->json(['success' => true]);
    }

    #[Route('/reservation/etape5AddSeat', name: 'etape5AddSeat', methods: ['POST'])]
    public function etape5AddSeat(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$this->checkCsrfOrJsonError($input['csrf_token'] ?? '', 'reservation_etape5')) return;

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $seatId = (int)($input['seat_id'] ?? 0);
        $index = (int)($input['index'] ?? -1);
        if ($seatId <= 0 || $index < 0) {
            $this->json(['success' => false, 'error' => 'Paramètres manquants.']);
            return;
        }

        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $nbPlacesAssises = $this->countPlacesAssises($reservation['reservation_detail'] ?? [], $tarifs);
        if ($index >= $nbPlacesAssises) {
            $this->json(['success' => false, 'error' => 'Index de participant invalide.']);
            return;
        }

        // Vérifier que la place existe et est ouverte
        $place = $this->placesRepository->findById($seatId);
        if (!$place || !$place->isOpen()) {
            $this->json(['success' => false, 'error' => "Place invalide ou fermée."]);
            return;
        }

        // Vérifier qu'elle n'est pas déjà prise (temporaire ou définitive)
        $tempSeats = $this->tempRepo->findAll();
        foreach ($tempSeats as $t) {
            if ($t->getPlaceId() == $seatId && $t->getSession() !== $sessionId) {
                $this->json(['success' => false, 'error' => "Place déjà en cours de réservation."]);
                return;
            }
        }

        // Insérer la réservation temporaire
        $now = new \DateTime();
        $timeout = (clone $now)->add(new \DateInterval(TIMEOUT_PLACE_RESERV));
        if (!$this->tempRepo->insertTempReservation($sessionId, $seatId, $index, $now, $timeout)) {
            return;
        }
        //Mise à jour de $_SESSION avec la place du participant à l'index donné
        $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_id'] = $seatId;
        $_SESSION['reservation'][$sessionId]['reservation_detail'][$index]['seat_name'] = $place ? $place->getFullPlaceName() : $seatId;


        $newToken = $this->getCsrfToken();
        $this->json([
            'success' => true,
            'csrf_token' => $newToken,
            'session' => $_SESSION['reservation'][$sessionId]
        ]);
    }

    #[Route('/reservation/etape5RemoveSeat', name: 'etape5RemoveSeat', methods: ['POST'])]
    public function etape5RemoveSeat(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$this->checkCsrfOrJsonError($input['csrf_token'] ?? '', 'reservation_etape5')) return;

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $seatId = (int)($input['seat_id'] ?? 0);
        if ($seatId <= 0) {
            $this->json(['success' => false, 'error' => 'Paramètre manquant.']);
            return;
        }

        // Supprimer la réservation temporaire pour cette session et cette place
        $this->tempRepo->deleteBySessionAndPlace($sessionId, $seatId);

        //Mise à jour de $_SESSION avec la place du participant à retirer à l'index donné
        if (isset($_SESSION['reservation'][$sessionId]['reservation_detail'])) {
            foreach ($_SESSION['reservation'][$sessionId]['reservation_detail'] as &$detail) {
                if (($detail['seat_id'] ?? null) == $seatId) {
                    $detail['seat_id'] = null;
                    $detail['seat_name'] = null;
                }
            }
            unset($detail);
        }

        $newToken = $this->getCsrfToken();
        $this->json([
            'success' => true,
            'csrf_token' => $newToken,
            'session' => $_SESSION['reservation'][$sessionId]
        ]);
    }

    /**
     * Compte le nombre de places assises attendues.
     */
    private function countPlacesAssises(array $reservationDetails, array $tarifs): int
    {
        $nb = 0;
        // Création d'une carte pour une recherche rapide des tarifs par ID
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }
        foreach ($reservationDetails as $detail) {
            $tarifId = null;
            if (is_object($detail) && $detail instanceof \app\Models\Reservation\ReservationsDetails) {
                $tarifId = $detail->getTarif();
            } elseif (is_array($detail) && isset($detail['tarif_id'])) {
                $tarifId = $detail['tarif_id'];
            }
            if ($tarifId !== null) {
                $tarif = $tarifsById[$tarifId] ?? null;
                if ($tarif && $tarif->getNbPlace() !== null) {
                    $nb++;
                }
            }
        }
        return $nb;
    }

    /**
     * pour rafraichir le contexte avec fetch
     */
    #[Route('/reservation/display-details-fragment', name: 'display_details_fragment', methods: ['GET'])]
    public function displayDetailsFragment(): void
    {
        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $this->render('reservation/_display_details', $context, '', true);
    }

    #[Route('/reservation/etape6Display', name: 'etape6Display', methods: ['GET'])]
    public function etape6Display(): void
    {
        $reservation = $_SESSION['reservation'][session_id()] ?? null;
        //Ne sert plus à cette étape
        unset($_SESSION['reservation'][session_id()]['selected_seats']);

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

        //Filtre les item sans places assises
        $context = ReservationContextHelper::getContext($this->eventsRepository, $this->tarifsRepository, $_SESSION['reservation'][session_id()] ?? null);
        $tarifsSansPlaces = array_filter($context['tarifs'], fn($t) => $t->getNbPlace() === null);

        //Récupération des tarifs sans places assises éventuellement déjà saisis pour pré remplissage
        $reservationComplement = [];
        if (!empty($reservation['reservation_complement'])) {
            foreach ($reservation['reservation_complement'] as $item) {
                $reservationComplement[$item['tarif_id']] = $item['qty'];
            }
        }
        $this->render('reservation/etape6', array_merge($context, [
            'tarifsSansPlaces' => $tarifsSansPlaces,
            'csrf_token' => $this->getCsrfToken(),
            'reservation' => $reservation,
            'reservationComplement' => $reservationComplement
        ]), 'Réservations');
    }

    #[Route('/reservation/etape6', name: 'etape6', methods: ['POST'])]
    public function etape6(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$this->checkCsrfOrJsonError($input['csrf_token'] ?? '', 'reservation_etape6')) return;

        $sessionId = session_id();
        $reservation = $_SESSION['reservation'][$sessionId] ?? null;
        if (!$reservation || empty($reservation['event_id'])) {
            $this->json(['success' => false, 'error' => 'Session expirée.']);
            return;
        }

        $tarifs = $this->tarifsRepository->findByEventId($reservation['event_id']);
        $tarifsSansPlaces = array_filter($tarifs, fn($t) => $t->getNbPlace() === null);
        $tarifIdsSansPlaces = array_map(fn($t) => $t->getId(), $tarifsSansPlaces);

        $tarifsInput = $input['tarifs'] ?? [];
        $reservationComplement = [];
        foreach ($tarifsInput as $t) {
            $id = (int)($t['id'] ?? 0);
            $qty = (int)($t['qty'] ?? 0);
            if ($qty > 0 && in_array($id, $tarifIdsSansPlaces, true)) {
                $reservationComplement[] = ['tarif_id' => $id, 'qty' => $qty];
            }
        }

        // Enregistrement dans reservation_complement (même si vide)
        $_SESSION['reservation'][$sessionId]['reservation_complement'] = $reservationComplement;

        $this->json(['success' => true]);
    }

}