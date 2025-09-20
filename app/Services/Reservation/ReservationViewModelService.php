<?php

namespace app\Services\Reservation;

use app\Repository\Event\EventsRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\TarifRepository;
use app\Services\NageuseService;
use app\Services\TarifService;
use DateInterval;
use DateMalformedStringException;
use DateTime;

class ReservationViewModelService
{
    private EventsRepository $eventsRepository;
    private NageusesRepository $nageusesRepository;
    private TarifRepository $tarifsRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private ReservationsPlacesTempRepository $tempRepo;
    private PiscineGradinsZonesRepository $zonesRepository;
    private PiscineGradinsPlacesRepository $placesRepository;
    private ReservationSessionService $reservationSessionService;
    private NageuseService $nageuseService;
    private TarifService $tarifService;
    private ReservationCartService $reservationCartService;

    public function __construct()
    {
        $this->eventsRepository = new EventsRepository();
        $this->nageusesRepository = new NageusesRepository();
        $this->tarifsRepository = new TarifRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->tempRepo = new ReservationsPlacesTempRepository();
        $this->zonesRepository = new PiscineGradinsZonesRepository();
        $this->placesRepository = new PiscineGradinsPlacesRepository();
        $this->reservationSessionService = new ReservationSessionService();
        $this->nageuseService = new NageuseService();
        $this->tarifService = new TarifService();
        $this->reservationCartService = new ReservationCartService();
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
            'tarifQuantities' => $this->reservationCartService->getTarifQuantitiesFromDetails($reservationDetails, $tarifs),
            'specialTarifSession' => $this->tarifService->findSpecialTarifInDetails($reservationDetails, $tarifs),
        ];
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

        // Suppression des réservations temporaires expirées pour tous les utilisateurs
        $this->tempRepo->deleteExpired((new DateTime())->format('Y-m-d H:i:s'));

        // Récupérer les places temporaires valides UNIQUEMENT pour la session de l'utilisateur courant
        $validTempSeatsForUser = $this->tempRepo->findAllSeatsBySession($sessionId);
        $validTempSeatIds = array_map(fn($t) => $t->getPlaceId(), $validTempSeatsForUser);

        // Vérifier et nettoyer les places en session de l'utilisateur
        $newTimeout = (new DateTime())->add(new DateInterval(TIMEOUT_PLACE_RESERV));
        foreach ($reservationData['reservation_detail'] as &$detail) {
            if (!empty($detail['seat_id'])) {
                if (in_array($detail['seat_id'], $validTempSeatIds)) {
                    // La place est toujours bien réservée, on rafraîchit son timeout
                    $this->tempRepo->updateTimeoutForSessionAndPlace($sessionId, $detail['seat_id'], $newTimeout);
                } else {
                    // La place a expiré, on la retire de la session.
                    $detail['seat_id'] = null;
                    $detail['seat_name'] = null;
                }
            }
        }
        unset($detail);

        // Récupérer les places déjà réservées de manière définitive
        $placesReservees = $this->reservationsDetailsRepository->findReservedSeatsForSession($reservationData['event_session_id']);

        // Récupérer TOUTES les places temporaires restantes (pour l'affichage des places prises par d'autres)
        $tempSeats = $this->tempRepo->findByEventSession($reservationData['event_session_id']);
        $placesSessions = [];
        foreach ($tempSeats as $t) {
            $placesSessions[$t->getPlaceId()] = $t->getSession();
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
            'nbPlacesAssises' => $this->reservationCartService->countSeatedPlaces($reservationData['reservation_detail'], $baseViewModel['tarifs']),
            'reservation' => $this->reservationSessionService->getReservationSession() // On recharge la session au cas où elle a été modifiée
        ]);
    }


}