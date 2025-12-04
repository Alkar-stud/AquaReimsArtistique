<?php
namespace app\Services\Reservation;

use app\Core\Database;
use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationComplement;
use app\Models\Reservation\ReservationComplementTemp;
use app\Models\Reservation\ReservationDetail;
use app\Models\Reservation\ReservationDetailTemp;
use app\Models\Reservation\ReservationTemp;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Repository\Reservation\ReservationTempRepository;
use app\Services\Log\Logger;
use app\Services\Mails\MailPrepareService;
use app\Services\Mails\MailService;
use app\Services\Payment\PaymentRecordService;
use app\Services\Security\TokenGenerateService;
use app\Services\UploadService;
use DateTime;
use RuntimeException;
use Throwable;

readonly class ReservationDataPersist
{
    private TokenGenerateService $tokenGenerateService;
    private ReservationComplementRepository $reservationsComplementsRepository;
    private ReservationRepository $reservationRepository;
    private Reservation $reservation;
    private ReservationDetailRepository $reservationDetailRepository;
    private PaymentRecordService $paymentRecordService;
    private EventSessionRepository $eventSessionRepository;
    private ReservationPaymentRepository $reservationPaymentRepository;
    private UploadService $uploadService;
    private MailService $mailService;
    private MailPrepareService $mailPrepareService;
    private ReservationTempRepository $reservationTempRepository;

    public function __construct(
        TokenGenerateService $tokenGenerateService,
        ReservationComplementRepository $reservationsComplementsRepository,
        ReservationRepository $reservationRepository,
        Reservation $reservation,
        ReservationDetailRepository $reservationDetailRepository,
        PaymentRecordService $paymentRecordService,
        EventSessionRepository $eventSessionRepository,
        ReservationPaymentRepository $reservationPaymentRepository,
        UploadService $uploadService,
        MailService $mailService,
        MailPrepareService $mailPrepareService,
        ReservationTempRepository $reservationTempRepository,
    ) {
        $this->tokenGenerateService = $tokenGenerateService;
        $this->reservationsComplementsRepository = $reservationsComplementsRepository;
        $this->reservationRepository = $reservationRepository;
        $this->reservation = $reservation;
        $this->reservationDetailRepository = $reservationDetailRepository;
        $this->paymentRecordService = $paymentRecordService;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->reservationPaymentRepository = $reservationPaymentRepository;
        $this->uploadService = $uploadService;
        $this->mailService = $mailService;
        $this->mailPrepareService = $mailPrepareService;
        $this->reservationTempRepository = $reservationTempRepository;
    }

    /**
     * Persiste une réservation complète en base de données MySQL à partir des données de paiement et de la réservation temporaire.
     *
     * @param object $paymentData Les données de la commande/paiement reçues de HelloAsso (le contenu de $result→data).
     * @param ReservationTemp $reservationTemp La réservation temporaire récupérée depuis MySQL.
     * @param string $context
     * @param bool $freeReservation
     * @return Reservation|null L'objet Reservation persistant ou null en cas d'erreur.
     */
    public function persistConfirmReservation(object $paymentData, ReservationTemp $reservationTemp, string $context, bool $freeReservation = false): ?Reservation
    {
        $pdo = Database::getInstance();

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            // Réservation principale
            $reservation = $this->createMainReservationObject($reservationTemp, $paymentData);
            if (!$reservation) {
                throw new RuntimeException('Réservation invalide.');
            }

            $newReservationId = $reservation->getId();
            if ($newReservationId <= 0) {
                throw new RuntimeException('Échec insertion réservation.');
            }

            // Détails + compléments (échec ⇛ exception ⇛ rollback)
            $this->persistDetails($newReservationId, $reservationTemp->getDetails());
            $this->persistComplements($newReservationId, $reservationTemp->getComplements());
            //On hydrate les 2 objets dans Reservation
            $reservation->setDetails($this->reservationDetailRepository->findByReservation($newReservationId, false, true, true));
            $reservation->setComplements($this->reservationsComplementsRepository->findByReservation($newReservationId, false, true));

            if (!$freeReservation) {
                // Détail du paiement
                $this->paymentRecordService->createPaymentRecord($newReservationId, $paymentData, $context);
                //On hydrate l'objet dans Reservation
                $reservation->setPayments($this->reservationPaymentRepository->findByReservation($newReservationId));
            }

            // Envoyer l'email de confirmation
            if (!$this->mailPrepareService->sendReservationConfirmationEmail($reservation)) {
                throw new RuntimeException('Échec de l\'envoi de l\'email de confirmation.');
            }

            // Enregistrer l'envoi de l'email
            $this->mailService->recordMailSent($reservation, 'paiement_confirme');

            // Nettoyer les données temporaires
            $this->cleanupTemporaryData($reservationTemp);

            // Commit si tout est OK
            $pdo->commit();

            // Relecture hydratée complète
            return $this->reservationRepository->findById($newReservationId, true, true, true);
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::get()->error('persistConfirmReservation', $e->getMessage(), (array)'ReservationDataPersist::persistConfirmReservation');
            return null;
        }
    }


    /**
     * Crée et insère l'enregistrement principal de la réservation.
     *
     * @param ReservationTemp $tempReservation
     * @param object|null $paymentData
     * @return Reservation|null
     */
    private function createMainReservationObject(ReservationTemp $tempReservation, ?object $paymentData = null): ?Reservation
    {
        $eventRepository = new EventRepository();
        $event = $eventRepository->findById($tempReservation->getEvent(), true, true, true, true);
        if (!$event) {
            error_log("Événement non trouvé pour la persistance de la réservation.");
            return null;
        }

        $sessionObj = null;
        foreach ($event->getSessions() as $s) {
            if ($s->getId() == $tempReservation->getEventSession()) {
                $sessionObj = $s;
                break;
            }
        }
        if (!$sessionObj) {
            error_log("Session de l'événement non trouvée pour la persistance.");
            return null;
        }

        $inscriptionDateToUse = null;
        $accessCode = $tempReservation->getAccessCode()?? null;
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
        $tokenGenerated = $this->tokenGenerateService->generateToken(32, null, $closeRegistrationDate);

        $this->reservation->setEvent($tempReservation->getEvent())
            ->setEventSession($tempReservation->getEventSession())
            ->setReservationTempId($tempReservation->getId())
            ->setName($tempReservation->getName())
            ->setFirstName($tempReservation->getFirstName())
            ->setEmail($tempReservation->getEmail())
            ->setPhone($tempReservation->getPhone())
            ->setSwimmerId($tempReservation->getSwimmerId())
            ->setTotalAmount($tempReservation->getTotalAmount())
            ->setTotalAmountPaid($paymentData->amount->total ?? 0)
            ->setToken($tokenGenerated['token'])
            ->setTokenExpireAt($tokenGenerated['expires_at_str'])
            ->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));

        // Hydrate les objets nécessaires pour l'envoi d'email plus tard
        $this->reservation->setEventObject($event);
        $this->reservation->setEventSessionObject($this->eventSessionRepository->findById($this->reservation->getEventSession()));

        $newReservationId = $this->reservationRepository->insert($this->reservation);
        $this->reservation->setId($newReservationId);

        return $this->reservation;
    }

    /**
     * Insère détails
     *
     * @param int $newReservationId
     * @param ReservationDetailTemp[] $reservationDetailTemp
     */
    private function persistDetails(int $newReservationId, array $reservationDetailTemp): void
    {
        foreach ($reservationDetailTemp as $detailData) {
            $detail = (new ReservationDetail())
                ->setReservation($newReservationId)
                ->setName($detailData->getName())
                ->setFirstName($detailData->getFirstName())
                ->setTarif($detailData->getTarif())
                ->setTarifAccessCode($detailData->getTarifAccessCode())
                ->setJustificatifName($detailData->getJustificatifName())
                ->setPlaceNumber($detailData->getPlaceNumber());

            $id = $this->reservationDetailRepository->insert($detail);
            if ($id <= 0) {
                throw new RuntimeException('Échec insertion détail.');
            }
        }
    }

    /**
     * Insère compléments.
     *
     * @param int $newReservationId
     * @param ReservationComplementTemp[] $reservationComplementTemp
     */
    private function persistComplements(int $newReservationId, array $reservationComplementTemp): void
    {
        foreach ($reservationComplementTemp as $complementData) {
            $complement = (new ReservationComplement())
                ->setReservation($newReservationId)
                ->setTarif($complementData->getTarif())
                ->setTarifAccessCode($complementData->getTarifAccessCode())
                ->setQty($complementData->getQty());
            $id = $this->reservationsComplementsRepository->insert($complement);
            if ($id <= 0) {
                throw new RuntimeException('Échec insertion complément.');
            }
        }
    }

    /**
     * Nettoie les données temporaires (NoSQL et MySQL).
     * Déplace les fichiers justificatifs de proofs/temp vers proofs
     * @param ReservationTemp $tempReservation
     */
    private function cleanupTemporaryData(ReservationTemp $tempReservation): void
    {
        //On déplace les fichiers justificatifs hors du dossier temp
        foreach ($tempReservation->getDetails() as $detailGroup) {
            // On utilise le nom de fichier unique généré lors de l'upload
            $this->uploadService->moveProofFile($detailGroup->getJustificatifName());
        }

        $this->reservationTempRepository->delete($tempReservation->getId());
    }

}
