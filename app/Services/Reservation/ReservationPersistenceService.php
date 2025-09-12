<?php

namespace app\Services\Reservation;

use app\Models\Reservation\ReservationMailsSent;
use app\Models\Reservation\ReservationPayments;
use app\Models\Reservation\Reservations;
use app\Models\Reservation\ReservationsComplements;
use app\Models\Reservation\ReservationsDetails;
use app\Repository\Event\EventsRepository;
use app\Repository\MailTemplateRepository;
use app\Repository\Reservation\ReservationMailsSentRepository;
use app\Repository\Reservation\ReservationPaymentsRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Services\MailService;
use app\Services\ReservationStorageInterface;
use DateMalformedStringException;
use DateTime;
use Exception;

/**
 * Service pour encapsuler la logique de persistance d'une réservation après un paiement réussi.
 */
class ReservationPersistenceService
{
    private ReservationStorageInterface $reservationStorage;
    private ReservationTokenService $reservationTokenService;
    private ReservationsPlacesTempRepository $reservationsPlacesTempRepository;

    public function __construct(
        ReservationStorageInterface      $reservationStorage,
        ReservationTokenService          $reservationTokenService,
        ReservationsPlacesTempRepository $reservationsPlacesTempRepository)
    {
        $this->reservationStorage = $reservationStorage;
        $this->reservationTokenService = $reservationTokenService;
        $this->reservationsPlacesTempRepository = $reservationsPlacesTempRepository;
    }

    /**
     * Persiste une réservation complète en base de données MySQL à partir des données de paiement et de la réservation temporaire.
     *
     * @param object $paymentData Les données de la commande/paiement reçues de HelloAsso (le contenu de $result→data).
     * @param array $tempReservation La réservation temporaire récupérée depuis MongoDB.
     * @return Reservations|null L'objet Reservation persistant ou null en cas d'erreur.
     * @throws Exception
     */
    public function persistPaidReservation(object $paymentData, array $tempReservation): ?Reservations
    {
        // 1. Créer l'objet Reservation principal
        $reservation = $this->createMainReservationObject($tempReservation, $paymentData);
        if (!$reservation) {
            return null;
        }

        // --- Persistance des informations de paiement ---
        $paymentInfo = $paymentData->payments[0];
        $reservationPayment = new ReservationPayments();
        $reservationPayment->setReservation($reservation->getId())
            ->setAmountPaid($paymentInfo->amount)
            ->setCheckoutId($paymentData->checkoutIntentId)
            ->setOrderId($paymentData->id)
            ->setPaymentId($paymentInfo->id)
            ->setStatusPayment($paymentInfo->state)
            ->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));
        $reservationPaymentsRepository = new ReservationPaymentsRepository();
        $reservationPaymentsRepository->insert($reservationPayment);

        // 2. Persister les détails et compléments
        $this->persistDetailsAndComplements($reservation->getId(), $tempReservation);

        // 3. Envoyer l'email de confirmation et enregistrer l'envoi
        $this->sendAndRecordConfirmationEmail($reservation);

        // 4. Nettoyer les données temporaires
        $this->cleanupTemporaryData($tempReservation);

        return $reservation;
    }


    /**
     * Persiste une réservation gratuite (montant total = 0) en base de données.
     *
     * @param array $tempReservation La réservation temporaire récupérée.
     * @return Reservations|null L'objet Reservation persistant ou null en cas d'erreur.
     * @throws Exception
     */
    public function persistFreeReservation(array $tempReservation): ?Reservations
    {
        // 1. Créer l'objet Reservation principal (sans données de paiement)
        $reservation = $this->createMainReservationObject($tempReservation);
        if (!$reservation) {
            return null;
        }

        // 2. Persister les détails et compléments
        $this->persistDetailsAndComplements($reservation->getId(), $tempReservation);

        // 3. Envoyer l'email de confirmation et enregistrer l'envoi
        $this->sendAndRecordConfirmationEmail($reservation);

        // 4. Nettoyer les données temporaires
        $this->cleanupTemporaryData($tempReservation);

        return $reservation;
    }

    /**
     * Crée et insère l'enregistrement principal de la réservation.
     * @throws Exception
     */
    private function createMainReservationObject(array $tempReservation, ?object $paymentData = null): ?Reservations
    {
        $eventsRepository = new EventsRepository();
        $event = $eventsRepository->findById($tempReservation['event_id']);
        if (!$event) {
            error_log("Événement non trouvé pour la persistance de la réservation.");
            return null;
        }

        $sessionObj = null;
        foreach ($event->getSessions() as $s) {
            if ($s->getId() == $tempReservation['event_session_id']) {
                $sessionObj = $s;
                break;
            }
        }
        if (!$sessionObj) {
            error_log("Session de l'événement non trouvée pour la persistance.");
            return null;
        }

        $inscriptionDateToUse = null;
        $accessCode = $tempReservation['access_code'] ?? null;
        if ($accessCode) {
            foreach ($event->getInscriptionDates() as $inscriptionDate) {
                if ($inscriptionDate->getAccessCode() === $accessCode) { $inscriptionDateToUse = $inscriptionDate; break; }
            }
        }
        if (!$inscriptionDateToUse) {
            foreach ($event->getInscriptionDates() as $inscriptionDate) {
                if ($inscriptionDate->getAccessCode() === null) { $inscriptionDateToUse = $inscriptionDate; break; }
            }
        }
        $closeRegistrationDate = $inscriptionDateToUse ? $inscriptionDateToUse->getCloseRegistrationAt() : $sessionObj->getEventStartAt();
        $tokenGenerated = $this->reservationTokenService->createReservationToken(32, $sessionObj->getEventStartAt(), $closeRegistrationDate);

        $reservation = new Reservations();
        $reservation->setEvent($tempReservation['event_id'])
            ->setUuid(bin2hex(random_bytes(16)))
            ->setEventSession($tempReservation['event_session_id'])
            ->setReservationMongoId((string) ($tempReservation['_id'] ?? $tempReservation['reservationId']))
            ->setNom($tempReservation['user']['nom'])
            ->setPrenom($tempReservation['user']['prenom'])
            ->setEmail($tempReservation['user']['email'])
            ->setPhone($tempReservation['user']['telephone'])
            ->setNageuseId($tempReservation['nageuse_id'] ?? null)
            ->setTotalAmount($tempReservation['total'] ?? 0)
            ->setTotalAmountPaid($paymentData->amount->total ?? 0)
            ->setToken($tokenGenerated[0])
            ->setTokenExpireAt($tokenGenerated[1])
            ->setComments($tempReservation['comments'] ?? null)
            ->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));

        // Hydrate l'objet Event pour l'envoi d'email plus tard
        $reservation->setEventObject($event);

        $reservationsRepository = new ReservationsRepository();
        $newReservationId = $reservationsRepository->insert($reservation);
        $reservation->setId($newReservationId);

        return $reservation;
    }

    /**
     * Persiste les détails (participants) et les compléments de la réservation.
     * @throws Exception
     */
    private function persistDetailsAndComplements(int $newReservationId, array $tempReservation): void
    {
        $reservationsDetailsRepository = new ReservationsDetailsRepository();
        foreach ($tempReservation['reservation_detail'] ?? [] as $detailData) {
            $reservationDetail = new ReservationsDetails();
            $reservationDetail->setReservation($newReservationId)
                ->setNom($detailData['nom'])
                ->setPrenom($detailData['prenom'])
                ->setTarif($detailData['tarif_id'])
                ->setTarifAccessCode($detailData['access_code'] ?? null)
                ->setJustificatifName($detailData['justificatif_name'] ?? null)
                ->setPlaceNumber($detailData['place_number'] ?? null)
                ->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));
            $reservationsDetailsRepository->insert($reservationDetail);
        }

        $reservationsComplementsRepository = new ReservationsComplementsRepository();
        foreach ($tempReservation['reservation_complement'] ?? [] as $complementData) {
            $reservationComplement = new ReservationsComplements();
            $reservationComplement->setReservation($newReservationId)
                ->setTarif($complementData['tarif_id'])
                ->setTarifAccessCode($complementData['access_code'] ?? null)
                ->setQty((int)$complementData['qty'])
                ->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));
            $reservationsComplementsRepository->insert($reservationComplement);
        }
    }

    /**
     * Envoie l'email de confirmation et enregistre l'envoi.
     * @throws DateMalformedStringException
     */
    private function sendAndRecordConfirmationEmail(Reservations $reservation): void
    {
        $mailService = new MailService();
        if ($mailService->sendReservationConfirmationEmail($reservation)) {
            $mailTemplateRepository = new MailTemplateRepository();
            $template = $mailTemplateRepository->findByCode('paiement_confirme');
            if ($template) {
                $mailSentRepository = new ReservationMailsSentRepository();
                $mailSentRecord = new ReservationMailsSent();
                $mailSentRecord->setReservation($reservation->getId())
                    ->setMailTemplate($template->getId())
                    ->setSentAt(date('Y-m-d H:i:s'));
                $mailSentRepository->insert($mailSentRecord);
            }
        } else {
            error_log("Failed to send initial confirmation email for reservation ID: " . $reservation->getId());
        }
    }

    /**
     * Nettoie les données temporaires (MongoDB et MySQL).
     */
    private function cleanupTemporaryData(array $tempReservation): void
    {
        $mongoId = (string) ($tempReservation['_id'] ?? $tempReservation['reservationId']);
        $this->reservationStorage->deleteReservation($mongoId);
        $userSessionId = $tempReservation['php_session_id'] ?? session_id();
        if ($userSessionId) {
            $this->reservationsPlacesTempRepository->deleteBySession($userSessionId);
        } else {
            error_log("Impossible de nettoyer les places temporaires pour la réservation mongo " . $tempReservation['_id'] . ": php_session_id manquant.");
        }
    }

}