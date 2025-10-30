<?php
namespace app\Services\Event;

use app\Models\Event\Event;
use app\Models\Event\EventInscriptionDate;
use app\Models\Piscine\Piscine;
use app\Models\Tarif\Tarif;
use app\Repository\Event\EventInscriptionDateRepository;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Piscine\PiscineRepository;
use app\Repository\Tarif\TarifRepository;
use DateTime;

class EventQueryService
{
    private EventRepository $eventRepository;
    private PiscineRepository $piscineRepository;
    private TarifRepository $tarifRepository;
    private EventInscriptionDateRepository $inscriptionDateRepository;
    private EventSessionRepository $eventSessionRepository;

    public function __construct(
        EventRepository $eventRepository,
        PiscineRepository $piscineRepository,
        TarifRepository $tarifRepository,
        EventInscriptionDateRepository $inscriptionDateRepository,
        EventSessionRepository $eventSessionRepository
    ) {
        $this->eventRepository = $eventRepository;
        $this->piscineRepository = $piscineRepository;
        $this->tarifRepository = $tarifRepository;
        $this->inscriptionDateRepository = $inscriptionDateRepository;
        $this->eventSessionRepository = $eventSessionRepository;
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


    public function findSessionById($sessionId): ?\app\Models\Event\EventSession
    {
        return $this->eventSessionRepository->findById($sessionId, true);
    }

 }