<?php

namespace app\Services\Reservation;

use app\Core\Paginator;
use app\Models\Piscine\Piscine;
use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationDetail;
use app\Models\Reservation\ReservationMailSent;
use app\Repository\Event\EventRepository;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationDetailTempRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Repository\Reservation\ReservationTempRepository;
use app\Services\Mails\MailPrepareService;
use app\Utils\StringHelper;
use DateTime;

class ReservationQueryService
{
    private ReservationRepository $reservationRepository;
    private EventRepository $eventRepository;
    private MailPrepareService $mailPrepareService;
    private ReservationPriceCalculator $priceCalculator;
    private ReservationComplementRepository $reservationComplementRepository;
    private ReservationDetailRepository $reservationDetailRepository;
    private ReservationDetailTempRepository $reservationDetailTempRepository;

    public function __construct(
        ReservationRepository $reservationRepository,
        EventRepository $eventRepository,
        MailPrepareService $mailPrepareService,
        ReservationPriceCalculator $priceCalculator,
        ReservationComplementRepository $reservationComplementRepository,
        ReservationDetailRepository $reservationDetailRepository,
        ReservationDetailTempRepository $reservationDetailTempRepository,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->eventRepository = $eventRepository;
        $this->mailPrepareService = $mailPrepareService;
        $this->priceCalculator = $priceCalculator;
        $this->reservationComplementRepository = $reservationComplementRepository;
        $this->reservationDetailRepository = $reservationDetailRepository;
        $this->reservationDetailTempRepository = $reservationDetailTempRepository;
    }

    /**
     * Compte le nombre de réservations actives pour un événement
     *
     * @param int $eventId L'ID de l'événement
     * @param int|null $swimmerId Si fourni, compte uniquement les réservations pour cette nageuse.
     * @return int
     */
    public function countActiveReservationsForThisEventAndThisSwimmer(int $eventId, ?int $swimmerId = null): int
    {
        return $this->reservationRepository->countReservationsForSwimmer($eventId, $swimmerId);
    }


    /**
     * Vérifie si des réservations existent pour un email et un événement, et agrège les informations.
     *
     * @param int $eventId
     * @param string $email
     * @return array
     */
    public function checkExistingReservationWithSameEmail(int $eventId, string $email): array
    {
        $event = $this->eventRepository->findById($eventId);
        if (!$event) {
            return ['exists' => false, 'error' => 'Événement invalide.'];
        }

        $reservations = $this->reservationRepository->findByEmailAndEvent($email, $eventId, false, true, true);
        if (empty($reservations)) {
            return ['exists' => false];
        }

        //On va chercher le nombre de places assises pour toutes les réservations, il suffit de compter et d'ajouter toutes les lignes de détails.
        $totalPlacesReserved = 0;
        $reservationSummaries = [];

        foreach ($reservations as $reserv) {
            $nbPlacesForThisReservation = count($reserv->getDetails());
            $totalPlacesReserved += $nbPlacesForThisReservation;
            $reservationSummaries[] = [
                'nb_places' => $nbPlacesForThisReservation,
                'session_date' => $reserv->getEventSessionObject()->getEventStartAt()->format('d/m/Y H:i')
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
     * Renvoie les emails de confirmation pour un événement et un email donnés.
     *
     * @param int $eventId
     * @param string $email
     * @return array
     */
    public function resendConfirmationEmails(int $eventId, string $email): array
    {
        if (empty($email) || empty($eventId)) {
            return ['success' => false, 'error' => 'Paramètres manquants.'];
        }

        $event = $this->eventRepository->findById($eventId);
        if (!$event) {
            return ['success' => false, 'error' => 'Événement invalide.'];
        }

        $reservations = $this->reservationRepository->findByEmailAndEvent($email, $eventId, true, true, true);
        if (empty($reservations)) {
            return ['success' => false, 'error' => 'Aucune réservation avec cet email pour cet événement.'];
        }

        $NbMailNotResent = 0;
        foreach ($reservations as $reservation) {
            //On compte combien d'email ont été envoyé pour cette raison
            $confirmationSentCount = 0;
            //On vérifie le nombre d'email envoyé pour cette raison
            foreach ($reservation->getMailSent() as $mailSent) {
                if ($mailSent->getMailTemplateObject()->getCode() == 'paiement_confirme') {
                    $confirmationSentCount++;
                }
            }
            if ($confirmationSentCount >= 2) { // Original + 1 renvoi
                $NbMailNotResent++;
                continue; // Limite atteinte, on passe au suivant
            }

            //On envoie le mail
            $sent = $this->mailPrepareService->sendReservationConfirmationEmail($reservation);
            if (!$sent) {
                return ['success' => false, 'error' => 'Erreur lors de l\'envoi du mail.'];
            }
            //On récupère le MailTemplate
            $mailTemplateRepository = new MailTemplateRepository();
            $mailTemplatePaiementConfirme = $mailTemplateRepository->findByCode('paiement_confirme');

            //On inscrit dans la table le mail envoyé
            $reservationMailSent = new ReservationMailSent();
            $reservationMailSent->setMailTemplate($mailTemplatePaiementConfirme->getId())
                                ->setReservation($reservation->getId())
                                ->setSentAt((new DateTime())->format('Y-m-d H:i:s'));

            $reservationMailSentRepository = new ReservationMailSentRepository();
            $insertId = $reservationMailSentRepository->insert($reservationMailSent);
            if ($insertId === 0) {
                return ['success' => false, 'error' => 'Échec d\'insertion en BDD.'];
            }
        }

        if ($NbMailNotResent > 0) {
            return ['success' => false, 'error' => 'Au moins 1 mail n\'a pas été renvoyé car cela avait déjà été fait (vérifiez vos spams).'];
        }
        return ['success' => true];
    }


    /**
     * Pour vérifier si la réservation peut être modifiée par le visiteur (annulation ou token expiré).
     *
     * @param Reservation $reservation
     * @return bool
     */
    public function checkIfReservationCanBeModified(Reservation $reservation): bool
    {
        $return = true;
        if ($reservation->isCanceled()) {
            $return = false;
        }
        if ($reservation->getTokenExpireAt() <= new DateTime()) {
            $return = false;
        }

        return $return;
    }


    /**
     * Pour préparer reservation détail et complement prête pour que la vue n'ait plus qu'à boucler sans faire de calcul
     *
     * @param Reservation $reservation
     * @return array
     */
    public function prepareReservationDetailsAndComplementsToView(Reservation $reservation): array
    {
        $readyForView = ['details' => [], 'complements' => []];

        // Grouper participants par tarif
        /** @var ReservationDetail $detail */
        foreach ($reservation->getDetails() as $detail) {
            $tarif = $detail->getTarifObject();
            $tarifId = $tarif->getId();

            if (!isset($readyForView['details'][$tarifId])) {
                $readyForView['details'][$tarifId] = [
                    'tarif' => $tarif,
                    'participants' => [],
                ];
            }

            $readyForView['details'][$tarifId]['participants'][] = [
                'id' => $detail->getId(),
                'name' => $detail->getName(),
                'firstname' => $detail->getFirstName(),
                'place_number' => $detail->getPlaceObject()?->getFullPlaceName(),
                'tarif_access_code' => method_exists($detail, 'getTarifAccessCode')
                    ? $detail->getTarifAccessCode()
                    : null,
            ];
        }

        // Calcul des totaux pour les détails
        $detailsSubtotal = 0;
        foreach ($readyForView['details'] as &$group) {
            $tarif = $group['tarif'];
            $price = $tarif->getPrice() ?? 0;
            $seatCount = $tarif->getSeatCount() ?? 0;
            $count = count($group['participants']);

            $calc = $this->priceCalculator->computeDetailTotals($count, $seatCount, $price);

            $group['price'] = $price;
            $group['seatCount'] = $seatCount;
            $group['count'] = $count;
            $group['packs'] = $calc['packs'];
            $group['total'] = $calc['total'];

            $detailsSubtotal += $group['total'];
        }
        unset($group);

        // Grouper compléments par tarif (somme des quantités)
        $complementBuckets = [];
        foreach ($reservation->getComplements() as $complement) {
            $tarif = $complement->getTarifObject();
            $tarifId = $tarif->getId();
            if (!isset($complementBuckets[$tarifId])) {
                $complementBuckets[$tarifId] = [
                    'tarif' => $tarif,
                    'qty' => 0,
                    'id'  => $complement->getId(),
                ];
            }
            $complementBuckets[$tarifId]['qty'] += (int)$complement->getQty();
        }

        // Calcul des totaux pour les compléments
        $complementsSubtotal = 0;
        foreach ($complementBuckets as $tid => $bucket) {
            $tarif = $bucket['tarif'];
            $qty = (int)$bucket['qty'];
            $price = $tarif->getPrice() ?? 0;
            $total = $this->priceCalculator->computeComplementTotal($qty, $price);

            $readyForView['complements'][$tid] = [
                'id'          => $bucket['id'],
                'tarif'       => $tarif,
                'qty'         => $qty,
                'price'       => $price,
                'total'       => $total,
            ];
            $complementsSubtotal += $total;
        }

        $readyForView['totals'] = [
            'details_subtotal' => $detailsSubtotal,
            'complements_subtotal' => $complementsSubtotal,
            'total_amount' => $detailsSubtotal + $complementsSubtotal,
        ];

        return $readyForView;
    }

    /**
     * Pour vérifier si un complément est déjà dans la réservation, permet de savoir si on ajoute ou update (dans le cas d'ajout de code access par exemple).
     *
     * @param Reservation $reservation
     * @param $tarif_id
     * @return bool
     */
    public function checkIfComplementIsAlreadyInReservation(Reservation $reservation, $tarif_id): bool
    {
        $result = $this->reservationComplementRepository->findByReservationAndTarif($reservation->getId(), $tarif_id) !== null;

        if ($result == null) {
            return false;
        }
        return true;
    }

    /**
     * Pour chercher dans plusieurs champs à l'aide du searchQuery de gestion/reservations
     *
     * @param string $searchQuery
     * @param int $currentPage
     * @param int $itemsPerPage
     * @param ReservationRepository|ReservationTempRepository $repo
     * @return Paginator|array
     */
    public function searchReservationsWithParam(string $searchQuery, int $currentPage, int $itemsPerPage, ReservationRepository|ReservationTempRepository $repo): Paginator|array
    {
        $q = trim($searchQuery);
        if ($q === '') {
            return [];
        }

        // Limiter par défaut pour éviter de gros résultats si non paginé côté appelant
        return $repo->findBySearchPaginated($q, $currentPage, $itemsPerPage, false, null);
    }

    /**
     * Retourne true | false si tous les participants sont cochés
     *
     * @param Reservation $reservation
     * @return bool
     */
    public function everyOneInReservationIsHere(Reservation $reservation): bool
    {
        $allParticipants = $this->reservationDetailRepository->findByReservation($reservation->getId(), false, false, false);
        $notEntered = count(array_filter($allParticipants, fn($p) => $p->getEnteredAt() === null));

        if ($notEntered > 0) { return false; }
        return true;
    }

    /**
     * Recherche pour l'entrée (nom/prénom ou ID uniquement)
     *
     * @param string $searchQuery
     * @return array ['single' => bool, 'reservations' => Reservation[]]
     */
    public function searchForEntrance(string $searchQuery): array
    {
        $q = trim($searchQuery);
        if ($q === '') {
            return ['single' => false, 'reservations' => []];
        }

        $reservations = $this->reservationRepository->findByNameOrId($q, 10);

        return [
            'single' => count($reservations) === 1,
            'reservations' => $reservations
        ];
    }

    /**
     * On récupère des éléments de réservation pour l'export en fonction des tarifs.
     *
     * @param array $tarifIds
     * @param array $checkedFields
     * @param int $selectedSessionId
     * @return array
     */
    public function getReservationsByTarifIds(array $tarifIds, array $checkedFields = [], int $selectedSessionId = 0): array
    {
        // champs par défaut si aucun n'est fourni
        if (empty($checkedFields)) {
            $searchRows = ['reservationId', 'name', 'firstName', 'email'];
        } else {
            //On prépare le tableau pour le repository
            $searchRows = [];
            foreach ($checkedFields as $field) {
                $searchRows = array_column($checkedFields, 'value');
            }
        }

        // On retourne le tableau avec les valeurs voulues
        return $this->reservationRepository->findByTarifIdsForExport($tarifIds, $searchRows, $selectedSessionId);
    }


    /**
     * Vérifie si la capacité totale de la piscine est atteinte et renvoi le nombre de places restantes
     * @param int $event_id
     * @param int $event_session_id
     * @return array
     */
    public function checkTotalCapacityLimit(int $event_id, int $event_session_id): array
    {
        //On récupère la capacité de la piscine
        $event = $this->eventRepository->findById($event_id, true);
        $piscine = $event->getPiscine();

        //On récupère le nombre de places actuellement réservées (et validées).
        $nbPlaceReserved = $this->reservationDetailRepository->countBySession($event_session_id, false, false, true);

        //On vérifie la différence
        if ($nbPlaceReserved >= $piscine->getMaxPlaces()) {
            return ['limitReached' => true, 'limit' => $piscine->getMaxPlaces() - $nbPlaceReserved];
        }

        return ['limitReached' => false, 'limit' => $piscine->getMaxPlaces() - $nbPlaceReserved];
    }

    /**
     * Retourne les zones et leurs places, plus les listes d'IDs par statut,
     * prêtes à être passées au contrôleur pour les vues piscine.
     *
     * - zones: PiscineGradinsZones[]
     * - placesByZone: array<int, PiscineGradinsPlaces[]>
     * - reservedPlaceIds, tempOtherPlaceIds, tempMyPlaceIds: int[]
     * - zoneUrlPattern, zonesListUrl, viewMode: strings
     *
     * @param Piscine $piscine
     * @return array
     */
    public function getAllSeatsInSwimmingPoolWithStatus(Piscine $piscine): array
    {
        $zonesRepo = new PiscineGradinsZonesRepository();
        $placesRepo = new PiscineGradinsPlacesRepository();

        // Récupère toutes les zones
        $zones = $zonesRepo->findByPiscine($piscine);

        // Indexe les places par zone et rattache l'objet zone à chaque place
        $placesByZone = [];
        foreach ($zones as $zone) {
            $zoneId = $zone->getId();
            $places = $placesRepo->findByFields(['zone' => $zoneId]);
            foreach ($places as $p) {
                $p->setZoneObject($zone);
            }
            $placesByZone[$zoneId] = $places;
        }

        return [
            'zones' => $zones,
            'placesByZone' => $placesByZone,
            'reservedPlaceIds' => [],
            'tempOtherPlaceIds' => [],
            'tempMyPlaceIds' => [],
            'zoneUrlPattern' => '/piscine/zone',
            'zonesListUrl' => '/piscine/zones',
            'viewMode' => 'readonly',
        ];
    }

    /**
     * Retourne le nombre de spectateurs inscrits par session
     *
     * @param array $events
     * @return array
     */
    public function getNbSpectatorsPerSession(array $events): array
    {
        $nbSpectatorsPerSession = [];
        foreach ($events as $event) {
            foreach ($event->getSessions() as $session) {
                $countDetail = $this->reservationDetailRepository->countBySession($session->getId());
                $nbSpectatorsPerSession[$session->getId()]['qty'] = $countDetail;
                if ($countDetail >= $event->getPiscine()->getMaxPlaces()) {
                    $nbSpectatorsPerSession[$session->getId()]['is_full'] = true;
                } else {
                    $nbSpectatorsPerSession[$session->getId()]['is_full'] = false;
                }
            }
        }
        return $nbSpectatorsPerSession;
    }

    /**
     * Pour récupérer toutes les places assises enregistrées et payées pour une session donnée.
     *
     * @param int $eventSessionId
     * @param bool $isForAdminView Si true, enrichit les données des places occupées.
     * @return array
     */
    public function getSeatStates(int $eventSessionId, bool $isForAdminView = false): array
    {
        $seatStates = [];
        $currentUserSessionId = session_id();

        // Places déjà payées (statut le plus prioritaire)
        $occupiedSeats = $this->reservationDetailRepository->findReservedSeatsForSession($eventSessionId);
        if (!empty($occupiedSeats)) {
            if ($isForAdminView) {
                // Pour l'admin, on charge les objets Reservation pour avoir les détails
                $reservationIds = array_unique(array_values($occupiedSeats));
                $reservations = $this->reservationRepository->findByIds($reservationIds, true, false, false, true);
                $reservationsById = [];
                foreach ($reservations as $r) {
                    $reservationsById[$r->getId()] = $r;
                }

                foreach ($occupiedSeats as $seatId => $reservationId) {
                    if ($seatId && isset($reservationsById[$reservationId])) {
                        /** @var Reservation $reservation */
                        $reservation = $reservationsById[$reservationId];
                        $seatStates[$seatId] = [
                            'status' => 'occupied',
                            'reservationId' => $reservation->getId(),
                            'reservationNumber' => StringHelper::generateReservationNumber($reservation->getId()),
                            'reserverName' => $reservation->getFirstName() . ' ' . $reservation->getName(),
                            'reservationSeatCount' => count($reservation->getDetails()),
                        ];
                    }
                }
            } else {
                // Pour le public, on met juste le statut
                foreach ($occupiedSeats as $seatId => $reservationId) {
                    if ($seatId) {
                        $seatStates[$seatId] = ['status' => 'occupied'];
                    }
                }
            }
        }

        // Places en cours de réservation dans les paniers
        $inCartSeats = $this->reservationDetailTempRepository->findSeatStatesForSession($eventSessionId);
        foreach ($inCartSeats as $seatId => $seatSessionId) {
            if (!isset($seatStates[$seatId])) {
                if ($seatSessionId === $currentUserSessionId) {
                    $seatStates[$seatId] = ['status' => 'in_cart_session'];
                } else {
                    $seatStates[$seatId] = ['status' => 'in_cart_other'];
                }
            }
        }

        return $seatStates;
    }
}