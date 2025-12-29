<?php
namespace app\Services\Event;

use app\Models\Event\Event;
use app\Models\Event\EventInscriptionDate;
use app\Models\Event\EventSession;
use app\Models\Piscine\Piscine;
use app\Models\Reservation\Reservation;
use app\Models\Tarif\Tarif;
use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Piscine\PiscineRepository;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Repository\Tarif\TarifRepository;
use DateTime;

class EventQueryService
{
    private EventRepository $eventRepository;
    private PiscineRepository $piscineRepository;
    private TarifRepository $tarifRepository;
    private EventInscriptionDateRepository $inscriptionDateRepository;
    private EventSessionRepository $eventSessionRepository;
    private ReservationRepository $reservationRepository;

    public function __construct(
        EventRepository $eventRepository,
        PiscineRepository $piscineRepository,
        TarifRepository $tarifRepository,
        EventInscriptionDateRepository $inscriptionDateRepository,
        EventSessionRepository $eventSessionRepository,
        ReservationRepository $reservationRepository,
    ) {
        $this->eventRepository = $eventRepository;
        $this->piscineRepository = $piscineRepository;
        $this->tarifRepository = $tarifRepository;
        $this->inscriptionDateRepository = $inscriptionDateRepository;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * Récupère tous les événements avec leurs relations chargées
     *
     * @param bool|null $isUpComing
     * @return Event[]
     */
    public function getAllEventsWithRelations(?bool $isUpComing = null): array
    {
        // Récupérer tous les événements de base
        $events = $this->eventRepository->findAllSortByDate($isUpComing);
        if (empty($events)) {
            return [];
        }
        // Créer une map des événements par ID pour un accès facile
        $eventsById = [];
        foreach ($events as $event) {
            $eventsById[$event->getId()] = $event;
        }
        $eventIds = array_keys($eventsById);

        // Récupérer toutes les relations en une seule fois
        $piscineIds = array_map(fn(Event $e) => $e->getPlace(), $events);
        $piscines = $this->piscineRepository->findByIds(array_unique($piscineIds));
        $piscinesById = [];
        foreach ($piscines as $piscine) {
            $piscinesById[$piscine->getId()] = $piscine;
        }

        $tarifsByEventId = $this->tarifRepository->findByEventIds($eventIds);
        $sessionsByEventId = $this->eventSessionRepository->findByEventIds($eventIds);

        $inscriptionDatesByEventId = $this->inscriptionDateRepository->findByEventIds($eventIds);

        // Attacher les relations aux objets Event correspondants
        foreach ($events as $event) {
            $eventId = $event->getId();

            // Attacher la piscine
            if (isset($piscinesById[$event->getPlace()])) {
                $event->setPiscine($piscinesById[$event->getPlace()]);
            }

            // Attacher les tarifs
            if (isset($tarifsByEventId[$eventId])) {
                $event->setTarifs($tarifsByEventId[$eventId]);
            }

            // Attacher les sessions
            if (isset($sessionsByEventId[$eventId])) {
                $event->setSessions($sessionsByEventId[$eventId]);
            }

            // Attacher les dates d'inscription
            if (isset($inscriptionDatesByEventId[$eventId])) {
                $event->setInscriptionDates($inscriptionDatesByEventId[$eventId]);
            }
        }

        return $events;
    }

    /**
     * Récupère toutes les piscines disponibles.
     *
     * @return Piscine[]
     */
    public function getAllPiscines(): array
    {
        return $this->piscineRepository->findAll();
    }

    /**
     * Récupère tous les tarifs actifs disponibles.
     *
     * @return Tarif[]
     */
    public function getAllActiveTarifs(): array
    {
        return $this->tarifRepository->findAllActive();
    }
    /**
     * Retourne les tarifs de l’événement enrichis avec l’indicateur d’usage et le compteur d’occurrences.
     *
     * Résultat indexé par ID de tarif.
     *
     * @param int $eventId ID de l’événement.
     * @return array<int,array{tarif: Tarif, isUsed: bool, usedCount: int}>
     */
    public function getEventTarifsWithUsage(int $eventId): array
    {
        // Tarifs attachés à l’événement (actifs, ordre logique déjà géré côté repo).
        $eventTarifs = $this->tarifRepository->findByEventId($eventId);
        if (empty($eventTarifs)) {
            return [];
        }

        $tarifIds = array_map(static fn(Tarif $t) => $t->getId(), $eventTarifs);

        $reservationDetailRepository = new ReservationDetailRepository();
        $usageMapDetails = $reservationDetailRepository->countUsageByTarifIdsForEvent($eventId, $tarifIds);
        $reservationComplementRepository = new ReservationComplementRepository();
        $usageMapComplement = $reservationComplementRepository->countUsageByTarifIdsForEvent($eventId, $tarifIds);

        $out = [];
        foreach ($eventTarifs as $tarif) {
            $tid = $tarif->getId();
            $countDetail = $usageMapDetails[$tid] ?? 0;
            $countComplement = $usageMapComplement[$tid] ?? 0;
            $totalUsage = $countDetail + $countComplement;
            $out[$tid] = [
                'isUsed' => $totalUsage > 0,
                'usedCount' => $totalUsage,
            ];

        }
        return $out;
    }

    /**
     * Pour récupérer la période d'inscription ouverte d'un événement.
     *
     * @param Event[] $events
     * @return array{periodesOuvertes: array<int, EventInscriptionDate>, nextPublicOuvertures: array<int, EventInscriptionDate>}
     */
     public function getEventInscriptionPeriodsStatus(array $events): array
     {
         $periodesOuvertes = [];
         $nextPublicOuvertures = [];
         $periodesCloses = [];
         $now = new DateTime();

         foreach ($events as $event) {
             $activePeriod = null;
             $activePublicPeriod = null;
             $nextPublicPeriod = null;

             // Les périodes sont déjà triées par start_registration_at dans le repository
             $inscriptionDates = $event->getInscriptionDates();

             foreach ($inscriptionDates as $date) {
                 $start = $date->getStartRegistrationAt();
                 $end = $date->getCloseRegistrationAt();

                 // Périodes actives
                 if ($start <= $now && $end > $now) {
                     // Mémorise la première période active trouvée
                     if ($activePeriod === null) {
                         $activePeriod = $date;
                     }
                     // Si période active sans code, on la privilégie
                     if ($date->getAccessCode() === null && $activePublicPeriod === null) {
                         $activePublicPeriod = $date;
                     }
                 }

                 // Prochaine période publique (sans code)
                 if ($date->getAccessCode() === null && $start > $now) {
                     if ($nextPublicPeriod === null || $start < $nextPublicPeriod->getStartRegistrationAt()) {
                         $nextPublicPeriod = $date;
                     }
                 }

                 // Dernière période close (pour le message)
                 if ($end < $now) {
                     $periodesCloses[$event->getId()] = $date;
                 }
             }

             // Si une période publique est active, on la choisit; sinon la première active
             if ($activePublicPeriod !== null) {
                 $periodesOuvertes[$event->getId()] = $activePublicPeriod;
             } elseif ($activePeriod !== null) {
                 $periodesOuvertes[$event->getId()] = $activePeriod;
             }

             if ($nextPublicPeriod) {
                 $nextPublicOuvertures[$event->getId()] = $nextPublicPeriod;
             }
         }

         return [
             'periodesOuvertes' => $periodesOuvertes,
             'nextPublicOuvertures' => $nextPublicOuvertures,
             'periodesCloses' => $periodesCloses,
         ];
     }

    /**
     * Valide un code d'accès pour un événement et vérifie si la période d'inscription est active.
     *
     * @param int $eventId L'ID de l'événement.
     * @param string $code Le code d'accès à valider.
     * @return array ['success' => bool, 'error' => ?string]
     */
    public function validateAccessCode(int $eventId, string $code): array
    {
        $periods = $this->inscriptionDateRepository->findByEventId($eventId);
        $now = new DateTime();

        foreach ($periods as $period) {
            // Comparaison sensible à la casse
            if ($period->getAccessCode() && $period->getAccessCode() === $code) {
                if ($now < $period->getStartRegistrationAt()) {
                    $dateLocale = $period->getStartRegistrationAt()->format('d/m/Y à H\hi');
                    return [
                        'success' => false,
                        'error' => "Ce code est valide, mais la période d'inscription n'a pas encore commencé. Ouverture le $dateLocale."
                    ];
                }

                if ($now > $period->getCloseRegistrationAt()) {
                    return [
                        'success' => false,
                        'error' => "Ce code est valide, mais la période d'inscription est terminée."
                    ];
                }

                // Le code est valide et la période est ouverte
                return ['success' => true, 'error' => null];
            }
        }

        return ['success' => false, 'error' => 'Code inconnu pour cet événement.'];
    }

     /**
      * Récupère et restructure les données des événements à venir pour l'affichage du tableau de bord.
      *
      * @return array
      */
     public function getStructuredUpcomingEventsForDashboard(): array
     {
         $flatUpcomingEvents = $this->eventRepository->getUpcomingEventsSessions();
         if (empty($flatUpcomingEvents)) {
             return [];
         }

        $upcomingEvents = [];
        // On restructure les données plates de la BDD en un tableau hiérarchique
        foreach ($flatUpcomingEvents as $row) {
            $eventId = $row['eventId'];
            $sessionId = $row['sessionId'];
            $periodId = $row['periodId'];

            // On crée l'événement s'il n'existe pas encore
            if (!isset($upcomingEvents[$eventId])) {
                $upcomingEvents[$eventId] = [
                    'id' => $eventId,
                    'name' => $row['eventName'],
                    'sessions' => [], // Sessions groupées par ID pour éviter doublons
                    'registrationPeriods' => [] // Périodes groupées par ID pour éviter doublons
                ];
            }

            // On crée la session si elle n'existe pas encore dans cet événement
            if (!isset($upcomingEvents[$eventId]['sessions'][$sessionId])) {
                $upcomingEvents[$eventId]['sessions'][$sessionId] = [
                    'id' => $sessionId,
                    'name' => $row['sessionName'],
                    'date' => $row['sessionDate'],
                ];
            }

            // On ajoute la période d'inscription si elle n'a pas déjà été ajoutée pour cet événement
            if ($periodId !== null && !isset($upcomingEvents[$eventId]['registrationPeriods'][$periodId])) {
                $upcomingEvents[$eventId]['registrationPeriods'][$periodId] = [
                    'name' => $row['periodName'],
                    'start' => $row['periodStart'],
                    'end' => $row['periodEnd'],
                ];
            }
        }

         // On nettoie les clés (ID) pour que la vue puisse boucler simplement avec foreach
         foreach ($upcomingEvents as &$event) {
             $event['sessions'] = array_values($event['sessions']);
             $event['registrationPeriods'] = array_values($event['registrationPeriods']);
         }

         return array_values($upcomingEvents);
    }

    /**
     * Pour récupérer une session par son ID.
     *
     * @param $sessionId
     * @return EventSession|null
     */
    public function findSessionById($sessionId): ?EventSession
    {
        return $this->eventSessionRepository->findById($sessionId, true);
    }

    /**
     * Calcule les stats par tarif et par session pour un event.
     *
     * @param int $eventId L'ID de l'événement pour lequel calculer les statistiques.
     * @return array Structure de données avec les statistiques par session et les totaux.
     */
    public function getTarifStatsForEvent(int $eventId): array
    {
        // On récupère tous les tarifs associés à l'événement.
        $eventTarifs = $this->tarifRepository->findByEventId($eventId);

        // On récupère toutes les réservations actives pour l'événement, avec tous leurs enfants.
        $reservations = $this->reservationRepository->findAllActiveByEvent($eventId, true);

        //On construit le tableau des statistiques à partir des réservations et de la liste complète des tarifs.
        return $this->buildStatsFromReservations($reservations, $eventTarifs);
    }

    /**
     * Construit une structure de statistiques à partir d'une liste de réservations.
     *
     * @param Reservation[] $reservations La liste des réservations.
     * @param Tarif[] $eventTarifs Tous les tarifs disponibles pour l'événement.
     * @return array
     */
    private function buildStatsFromReservations(array $reservations, array $eventTarifs): array
    {
        // Initialisation de la structure de sortie
        $stats = [
            'sessions' => [],
            'eventTotals' => [
                'seated' => ['persons' => 0, 'tickets' => 0, 'amount' => 0],
                'complements' => ['qty' => 0, 'amount' => 0],
                'grandTotal' => ['amount' => 0],
            ],
        ];

        // Structure de base pour une session
        $sessionTemplate = array(
            'sessionName' => '',
            'seated' => array('perTarif' => array(), 'totals' => array('persons' => 0, 'tickets' => 0, 'amount' => 0)),
            'complements' => array('perTarif' => array(), 'totals' => array('qty' => 0, 'amount' => 0)),
            'grandTotal' => array('amount' => 0),
        );

        foreach ($reservations as $reservation) {
            $sessionId = $reservation->getEventSession();
            //On récupère la session
            $session = $this->eventSessionRepository->findById($sessionId);

            // Initialiser la session si elle n'existe pas
            if (!isset($stats['sessions'][$sessionId])) {
                $stats['sessions'][$sessionId] = $sessionTemplate;
                $stats['sessions'][$sessionId]['sessionName'] = $session->getSessionName();

                // On pré rempli la session avec tous les tarifs de l'événement à zéro.
                foreach ($eventTarifs as $tarif) {
                    $tarifId = $tarif->getId();
                    $seatCount = $tarif->getSeatCount();

                    if ($seatCount !== null && $seatCount > 0) { // Tarif avec place
                        $stats['sessions'][$sessionId]['seated']['perTarif'][$tarifId] = [
                            'name' => $tarif->getName(),
                            'persons' => 0,
                            'tickets' => 0,
                            'seatCount' => $seatCount,
                            'price' => $tarif->getPrice(),
                            'amount' => 0,
                        ];
                    } else { // Tarif sans place (complément)
                        $stats['sessions'][$sessionId]['complements']['perTarif'][$tarifId] = [
                            'name' => $tarif->getName(),
                            'qty' => 0,
                            'price' => $tarif->getPrice(),
                            'amount' => 0,
                        ];
                    }
                }
            }

            // Traitement des détails (tarifs avec place)
            $detailsByTarif = [];
            foreach ($reservation->getDetails() as $detail) {
                $tarif = $detail->getTarifObject();
                if (!$tarif) continue;

                $tarifId = $tarif->getId();
                if (!isset($detailsByTarif[$tarifId])) {
                    $detailsByTarif[$tarifId] = [
                        'tarif' => $tarif,
                        'count' => 0,
                    ];
                }
                $detailsByTarif[$tarifId]['count']++;
            }

            foreach ($detailsByTarif as $tarifId => $data) {
                $tarif = $data['tarif'];
                $detailCount = $data['count'];
                $price = $tarif->getPrice();
                $seatCount = $tarif->getSeatCount() ?? 1;

                // Calculer le nombre de tickets (packs)
                $ticketCount = (int)ceil($detailCount / $seatCount);
                $totalPersons = $detailCount; // Chaque detail = 1 personne

                // Mettre à jour les stats pour ce tarif dans cette session
                $stats['sessions'][$sessionId]['seated']['perTarif'][$tarifId]['persons'] += $totalPersons;
                $stats['sessions'][$sessionId]['seated']['perTarif'][$tarifId]['tickets'] += $ticketCount;
                $stats['sessions'][$sessionId]['seated']['perTarif'][$tarifId]['amount'] += $price * $ticketCount;

                // Mettre à jour les totaux de la session
                $stats['sessions'][$sessionId]['seated']['totals']['persons'] += $totalPersons;
                $stats['sessions'][$sessionId]['seated']['totals']['tickets'] += $ticketCount;
                $stats['sessions'][$sessionId]['seated']['totals']['amount'] += $price * $ticketCount;

                // Mettre à jour les totaux de l'événement
                $stats['eventTotals']['seated']['persons'] += $totalPersons;
                $stats['eventTotals']['seated']['tickets'] += $ticketCount;
                $stats['eventTotals']['seated']['amount'] += $price * $ticketCount;
            }

            // Traitement des compléments (tarifs sans place)
            foreach ($reservation->getComplements() as $complement) {
                $tarif = $complement->getTarifObject();
                if (!$tarif) continue;

                $tarifId = $tarif->getId();
                $price = $tarif->getPrice();
                $quantity = $complement->getQty();

                // Mettre à jour les stats pour ce tarif dans cette session (le tarif est déjà initialisé)
                $stats['sessions'][$sessionId]['complements']['perTarif'][$tarifId]['qty'] += $quantity;
                $stats['sessions'][$sessionId]['complements']['perTarif'][$tarifId]['amount'] += $price * $quantity;

                // Mettre à jour les totaux de la session
                $stats['sessions'][$sessionId]['complements']['totals']['qty'] += $quantity;
                $stats['sessions'][$sessionId]['complements']['totals']['amount'] += $price * $quantity;

                // Mettre à jour les totaux de l'événement
                $stats['eventTotals']['complements']['qty'] += $quantity;
                $stats['eventTotals']['complements']['amount'] += $price * $quantity;
            }
        }

        // Calcul des grands totaux
        foreach ($stats['sessions'] as &$sessionStats) {
            $sessionStats['grandTotal']['amount'] = $sessionStats['seated']['totals']['amount'] + $sessionStats['complements']['totals']['amount'];
        }
        unset($sessionStats); // Important pour casser la référence

        $stats['eventTotals']['grandTotal']['amount'] = $stats['eventTotals']['seated']['amount'] + $stats['eventTotals']['complements']['amount'];

        return $stats;
    }
}