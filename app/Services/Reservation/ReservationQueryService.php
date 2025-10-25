<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationMailSent;
use app\Repository\Event\EventRepository;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailPrepareService;
use DateTime;

class ReservationQueryService
{
    private ReservationRepository $reservationRepository;
    private EventRepository $eventRepository;
    private MailPrepareService $mailPrepareService;
    private ReservationPriceCalculator $priceCalculator;
    private ReservationComplementRepository $reservationComplementRepository;

    public function __construct(
        ReservationRepository $reservationRepository,
        EventRepository $eventRepository,
        MailPrepareService $mailPrepareService,
        ReservationPriceCalculator $priceCalculator,
        ReservationComplementRepository $reservationComplementRepository,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->eventRepository = $eventRepository;
        $this->mailPrepareService = $mailPrepareService;
        $this->priceCalculator = $priceCalculator;
        $this->reservationComplementRepository = $reservationComplementRepository;
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
                'place_number' => method_exists($detail, 'getPlaceNumber')
                    ? $detail->getPlaceNumber()
                    : (method_exists($detail->getPlaceObject() ?: null, 'getId')
                        ? $detail->getPlaceObject()->getId()
                        : null),
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

}