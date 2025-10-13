<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationMailSent;
use app\Repository\Event\EventRepository;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailPrepareService;
use DateTime;

class ReservationQueryService
{
    private ReservationRepository $reservationRepository;
    private EventRepository $eventRepository;
    private MailPrepareService $mailPrepareService;

    public function __construct(
        ReservationRepository $reservationRepository,
        EventRepository $eventRepository,
        MailPrepareService $mailPrepareService,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->eventRepository = $eventRepository;
        $this->mailPrepareService = $mailPrepareService;
    }

    /**
     * Compte le nombre de réservations actives pour un événement
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
     * Pour vérifier si la réservation peut être modifiée par le visiteur (annulation ou date expirée).
     * @param Reservation $reservation
     * @return bool
     */
    public function checkIfReservationCanBeModified(Reservation $reservation): bool
    {
        $return = true;
        if ($reservation->isCanceled()) {
            $return = false;
        }
        $eventInscriptionDates = $reservation->getEventObject()->getInscriptionDates();
        //on prend la date la plus éloignée de fin d'inscription
        $dateEndInscription = null;
        foreach ($eventInscriptionDates as $inscriptionDate) {
            if ($inscriptionDate->getCloseRegistrationAt() > $dateEndInscription) {
                $dateEndInscription = $inscriptionDate->getCloseRegistrationAt();
            }
        }
        if ($dateEndInscription <= new DateTime()) {
            $return = false;
        }

        return $return;
    }


    /**
     * Pour préparer reservation détail et complement prête pour que la vue n'ait plus qu'à boucler sans faire de calcul
     * @param Reservation $reservation
     * @return array
     */
    public function prepareReservationDetailsAndComplementsToView(Reservation $reservation): array
    {
        //Pour les détails
        $readyForView = [];
        foreach ($reservation->getDetails() as $detail) {
            $readyForView[$detail->getTarifObject()->getId()][]['name'] = $detail->getName();


        }

echo '<pre>';
print_r($readyForView);
die;

        return $readyForView;
    }

}