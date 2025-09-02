<?php

namespace app\Services;

use app\Models\Reservation\ReservationMailsSent;
use app\Models\Reservation\Reservations;
use app\Models\Reservation\ReservationsDetails;
use app\Models\Reservation\ReservationsComplements;
use app\Models\Reservation\ReservationPayments;
use app\Repository\Event\EventsRepository;
use app\Repository\MailTemplateRepository;
use app\Repository\Reservation\ReservationMailsSentRepository;
use app\Repository\Reservation\ReservationsPlacesTempRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationPaymentsRepository;
use app\Utils\ReservationHelper;
use DateTime;
use Exception;

/**
 * Service pour encapsuler la logique de persistance d'une réservation après un paiement réussi.
 */
class ReservationPersistenceService
{
    private ReservationStorageInterface $reservationStorage;
    private ReservationHelper $reservationHelper;
    private ReservationsPlacesTempRepository $reservationsPlacesTempRepository;

    public function __construct(
        ReservationStorageInterface $reservationStorage,
        ReservationHelper $reservationHelper,
        ReservationsPlacesTempRepository $reservationsPlacesTempRepository)
    {
        $this->reservationStorage = $reservationStorage;
        $this->reservationHelper = $reservationHelper;
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
        $eventsRepository = new EventsRepository();
        $event = $eventsRepository->findById($tempReservation['event_id']);
        if (!$event) {
            // Log l'erreur, car on ne peut pas renvoyer de JSON ici.
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

        // Déterminer la date de fin d'inscription à utiliser en fonction du code d'accès
        $inscriptionDateToUse = null;
        $accessCode = $tempReservation['access_code'] ?? null;

        // On cherche une correspondance avec le code d'accès s'il existe
        if ($accessCode) {
            foreach ($event->getInscriptionDates() as $inscriptionDate) {
                if ($inscriptionDate->getAccessCode() === $accessCode) {
                    $inscriptionDateToUse = $inscriptionDate;
                    break;
                }
            }
        }

        // Si pas de code ou pas de correspondance, on cherche la date publique (sans code).
        if (!$inscriptionDateToUse) {
            foreach ($event->getInscriptionDates() as $inscriptionDate) {
                if ($inscriptionDate->getAccessCode() === null) {
                    $inscriptionDateToUse = $inscriptionDate;
                    break;
                }
            }
        }

        // On récupère la date de fin. En cas d'échec, on prend la date de l'événement par sécurité.
        $closeRegistrationDate = $inscriptionDateToUse ? $inscriptionDateToUse->getCloseRegistrationAt() : $sessionObj->getEventStartAt();
        $tokenGenerated = $this->reservationHelper->genereToken(32, $sessionObj->getEventStartAt(), $closeRegistrationDate);

        // --- Création de l'objet Reservation principal ---
        $reservation = new Reservations();
        $reservation->setEvent($tempReservation['event_id'])
            ->setUuid(bin2hex(random_bytes(16))) // Génération d'un UUID simple
            ->setEventSession($tempReservation['event_session_id'])
            ->setReservationMongoId((string) $tempReservation['_id'])
            ->setNom($tempReservation['user']['nom'])
            ->setPrenom($tempReservation['user']['prenom'])
            ->setEmail($tempReservation['user']['email'])
            ->setPhone($tempReservation['user']['telephone'])
            ->setNageuseId($tempReservation['nageuse_id'] ?? null)
            ->setTotalAmount($tempReservation['total'])
            ->setTotalAmountPaid($paymentData->amount->total)
            ->setToken($tokenGenerated[0])
            ->setTokenExpireAt($tokenGenerated[1])
            ->setComments($tempReservation['comments'] ?? null)
            ->setCreatedAt(new DateTime()->format('Y-m-d H:i:s'));

        $reservationsRepository = new ReservationsRepository();
        $newReservationId = $reservationsRepository->insert($reservation);
        $reservation->setId($newReservationId);

        // --- Persistance des détails (participants) ---
        $reservationsDetailsRepository = new ReservationsDetailsRepository();
        foreach ($tempReservation['reservation_detail'] ?? [] as $detailData) {
            $reservationDetail = new ReservationsDetails();
            $reservationDetail->setReservation($newReservationId)
                ->setNom($detailData['nom'])
                ->setPrenom($detailData['prenom'])
                ->setTarif($detailData['tarif_id'])
                ->setTarifAccessCode($detailData['access_code'] ?? null)
                ->setPlaceNumber($detailData['seat_id'])
                ->setCreatedAt(new DateTime()->format('Y-m-d H:i:s'));
            $reservationsDetailsRepository->insert($reservationDetail);
        }

        // --- Persistance des compléments ---
        $reservationsComplementsRepository = new ReservationsComplementsRepository();
        foreach ($tempReservation['reservation_complement'] ?? [] as $complementData) {
            $reservationComplement = new ReservationsComplements();
            $reservationComplement->setReservation($newReservationId)
                ->setTarif($complementData['tarif_id'])
                ->setTarifAccessCode($complementData['access_code'] ?? null)
                ->setQty((int)$complementData['qty'])
                ->setCreatedAt(new DateTime()->format('Y-m-d H:i:s'));
            $reservationsComplementsRepository->insert($reservationComplement);
        }

        // --- Persistance des informations de paiement ---
        $paymentInfo = $paymentData->payments[0];
        $reservationPayment = new ReservationPayments();
        $reservationPayment->setReservation($newReservationId)
            ->setAmountPaid($paymentInfo->amount)
            ->setCheckoutId($paymentData->checkoutIntentId)
            ->setOrderId($paymentData->id)
            ->setPaymentId($paymentInfo->id)
            ->setStatusPayment($paymentInfo->state)
            ->setCreatedAt(new DateTime()->format('Y-m-d H:i:s'));
        $reservationPaymentsRepository = new ReservationPaymentsRepository();
        $reservationPaymentsRepository->insert($reservationPayment);

        //Envoi du mail de confirmation

        $mailService = new MailService();
        $reservation->setEventObject($event); // Hydrate with event for the email service
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


        // --- Nettoyage mongoDB et la table reservations_places_temp ---
        $this->reservationStorage->deleteReservation($tempReservation['_id']->__toString());
        // L'identifiant de session de l'utilisateur a été stocké dans la réservation temporaire
        $userSessionId = $tempReservation['php_session_id'] ?? null;
        if ($userSessionId) {
            $this->reservationsPlacesTempRepository->deleteBySession($userSessionId);
        } else {
            // Log d'une erreur si l'ID de session n'est pas trouvé, ce qui serait anormal.
            error_log("Impossible de nettoyer les places temporaires pour la réservation mongo {$tempReservation['_id']}: php_session_id manquant.");
        }


        return $reservation;
    }
}