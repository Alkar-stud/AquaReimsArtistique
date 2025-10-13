<?php
namespace app\Services\Reservation;

use app\Core\Database;
use app\DTO\ReservationSelectionSessionDTO;
use app\DTO\ReservationUserDTO;
use app\DTO\ReservationDetailItemDTO;
use app\DTO\ReservationComplementItemDTO;
use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationComplement;
use app\Models\Reservation\ReservationDetail;
use app\Models\Reservation\ReservationMailSent;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationPlaceTempRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Log\Logger;
use app\Services\Mails\MailPrepareService;
use app\Services\Payment\PaymentRecordService;
use app\Services\Security\TokenGenerateService;
use DateTime;
use Throwable;

readonly class ReservationDataPersist
{
    private ReservationSessionService $reservationSessionService;
    private TokenGenerateService $tokenGenerateService;
    private ReservationComplementRepository $reservationsComplementsRepository;
    private ReservationRepository $reservationRepository;
    private Reservation $reservation;
    private ReservationDetailRepository $reservationDetailRepository;
    private PaymentRecordService $paymentRecordService;
    private EventSessionRepository $eventSessionRepository;
    private ReservationPaymentRepository $reservationPaymentRepository;
    private ReservationPlaceTempRepository $reservationPlaceTempRepository;
    private ReservationTempWriter $reservationTempWriter;

    public function __construct(
        ReservationSessionService $reservationSessionService,
        TokenGenerateService $tokenGenerateService,
        ReservationComplementRepository $reservationsComplementsRepository,
        ReservationRepository $reservationRepository,
        Reservation $reservation,
        ReservationDetailRepository $reservationDetailRepository,
        PaymentRecordService $paymentRecordService,
        EventSessionRepository $eventSessionRepository,
        ReservationPaymentRepository $reservationPaymentRepository,
        ReservationPlaceTempRepository $reservationPlaceTempRepository,
        ReservationTempWriter $reservationTempWriter,
    ) {
        $this->reservationSessionService = $reservationSessionService;
        $this->tokenGenerateService = $tokenGenerateService;
        $this->reservationsComplementsRepository = $reservationsComplementsRepository;
        $this->reservationRepository = $reservationRepository;
        $this->reservation = $reservation;
        $this->reservationDetailRepository = $reservationDetailRepository;
        $this->paymentRecordService = $paymentRecordService;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->reservationPaymentRepository = $reservationPaymentRepository;
        $this->reservationPlaceTempRepository = $reservationPlaceTempRepository;
        $this->reservationTempWriter = $reservationTempWriter;
    }

    /**
     * Persiste un DTO "unique" (étapes 1, 2, ou un complément) en session.
     * $key pour les clés de tableau
     *
     * @param ReservationSelectionSessionDTO|ReservationUserDTO|ReservationDetailItemDTO|ReservationComplementItemDTO $dto
     * @param int|null $key
     * @return void
     */
    public function persistDataInSession(
        ReservationSelectionSessionDTO|ReservationUserDTO|ReservationDetailItemDTO|ReservationComplementItemDTO $dto,
        ?int $key = null
    ): void {
        if ($dto instanceof ReservationSelectionSessionDTO) {
            // Étape 1
            $this->reservationSessionService->setReservationSession('event_id', $dto->eventId);
            $this->reservationSessionService->setReservationSession('event_session_id', $dto->eventSessionId);
            $this->reservationSessionService->setReservationSession('swimmer_id', $dto->swimmerId);
            $this->reservationSessionService->setReservationSession('access_code_used', $dto->access_code);
            $this->reservationSessionService->setReservationSession('limit_per_swimmer', $dto->limitPerSwimmer);
            return;
        }

        if ($dto instanceof ReservationUserDTO) {
            // Étape 2
            $this->reservationSessionService->setReservationSession(['booker', 'name'], $dto->name);
            $this->reservationSessionService->setReservationSession(['booker', 'firstname'], $dto->firstname);
            $this->reservationSessionService->setReservationSession(['booker', 'email'], $dto->email);
            $this->reservationSessionService->setReservationSession(['booker', 'phone'], $dto->phone);
            return;
        }

        if ($dto instanceof ReservationDetailItemDTO) {
            // Empile les détails
            $this->reservationSessionService->setReservationSession(['reservation_detail', $key, 'tarif_id'], $dto->tarif_id);
            $this->reservationSessionService->setReservationSession(['reservation_detail', $key, 'name'], $dto->name);
            $this->reservationSessionService->setReservationSession(['reservation_detail', $key, 'firstname'], $dto->firstname);
            $this->reservationSessionService->setReservationSession(['reservation_detail', $key, 'justificatif_name'], $dto->justificatif_name);
            $this->reservationSessionService->setReservationSession(['reservation_detail', $key, 'justificatif_original_name'], $dto->justificatif_original_name);
            $this->reservationSessionService->setReservationSession(['reservation_detail', $key, 'tarif_access_code'], $dto->tarif_access_code);
            $this->reservationSessionService->setReservationSession(['reservation_detail', $key, 'place_id'], $dto->place_id);
            return;
        }

        if ($dto instanceof ReservationComplementItemDTO) {
            // Empile les compléments sélectionnés
            $this->reservationSessionService->setReservationSession(['reservation_complement', $key, 'tarif_id'], $dto->tarif_id);
            $this->reservationSessionService->setReservationSession(['reservation_complement', $key, 'qty'], $dto->qty);
            $this->reservationSessionService->setReservationSession(['reservation_complement', $key, 'tarif_access_code'], $dto->tarif_access_code);
            return;
        }

    }

    /**
     * Persiste une réservation complète en base de données MySQL à partir des données de paiement et de la réservation temporaire.
     *
     * @param object $paymentData Les données de la commande/paiement reçues de HelloAsso (le contenu de $result→data).
     * @param array $tempReservation La réservation temporaire récupérée depuis NoSQL.
     * @param string $context
     * @param bool $freeReservation
     * @return Reservation|null L'objet Reservation persistant ou null en cas d'erreur.
     */
    public function persistConfirmReservation(object $paymentData, array $tempReservation, string $context, bool $freeReservation = false): ?Reservation
    {
        $pdo = Database::getInstance();

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            // Réservation principale
            $reservation = $this->createMainReservationObject($tempReservation, $paymentData);
            if (!$reservation) {
                throw new \RuntimeException('Réservation invalide.');
            }

            $newReservationId = $reservation->getId();
            if ($newReservationId <= 0) {
                throw new \RuntimeException('Échec insertion réservation.');
            }

            // Détails + compléments (échec ⇛ exception ⇛ rollback)
            $this->persistDetailsAndComplements($newReservationId, $tempReservation);
            //On hydrate les 2 objets dans Reservation
            $reservation->setDetails($this->reservationDetailRepository->findByReservation($newReservationId, false, true, true));
            $reservation->setComplements($this->reservationsComplementsRepository->findByReservation($newReservationId, false, true));

            if (!$freeReservation) {
                // Détail du paiement
                $this->paymentRecordService->createPaymentRecord($newReservationId, $paymentData, $context);
                //On hydrate l'objet dans Reservation
                $reservation->setPayments($this->reservationPaymentRepository->findByReservation($newReservationId));
            }

            // Envoyer l'email de confirmation et enregistrer l'envoi
            $this->sendAndRecordConfirmationEmail($reservation, 'paiement_confirme');

            // Nettoyer les données temporaires
            //$this->cleanupTemporaryData($tempReservation);

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
     * @param array $tempReservation
     * @param object|null $paymentData
     * @return Reservation|null
     */
    private function createMainReservationObject(array $tempReservation, ?object $paymentData = null): ?Reservation
    {
        $eventRepository = new EventRepository();
        $event = $eventRepository->findById($tempReservation['event_id'], true, true, true, true);
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
        $tokenGenerated = $this->tokenGenerateService->generateToken(32, null, $closeRegistrationDate);

        $this->reservation->setEvent($tempReservation['event_id'])
            ->setEventSession($tempReservation['event_session_id'])
            ->setReservationTempId((string) ($tempReservation['_id'] ?? $tempReservation['primary_id']))
            ->setName($tempReservation['booker']['name'])
            ->setFirstName($tempReservation['booker']['firstname'])
            ->setEmail($tempReservation['booker']['email'])
            ->setPhone($tempReservation['booker']['phone'])
            ->setSwimmerId($tempReservation['swimmer_id'] ?? null)
            ->setTotalAmount($tempReservation['total'] ?? 0)
            ->setTotalAmountPaid($paymentData->amount->total ?? 0)
            ->setToken($tokenGenerated['token'])
            ->setTokenExpireAt($tokenGenerated['expires_at_str'])
            ->setComments($tempReservation['comments'] ?? null)
            ->setCreatedAt((new DateTime())->format('Y-m-d H:i:s'));

        // Hydrate les objets nécessaires pour l'envoi d'email plus tard
        $this->reservation->setEventObject($event);
        $this->reservation->setEventSessionObject($this->eventSessionRepository->findById($this->reservation->getEventSession()));

        $newReservationId = $this->reservationRepository->insert($this->reservation);
        $this->reservation->setId($newReservationId);

        return $this->reservation;
    }

    /**
     * Insère détails et compléments.
     *
     * @param int $newReservationId
     * @param array $tempReservation
     */
    private function persistDetailsAndComplements(int $newReservationId, array $tempReservation): void
    {
        foreach ($tempReservation['reservation_detail'] ?? [] as $tarifId => $detailData) {
            foreach ($detailData['participants'] as $participant) {
                $detail = (new ReservationDetail())
                    ->setReservation($newReservationId)
                    ->setName($participant['name'] ?? null)
                    ->setFirstName($participant['firstname'] ?? null)
                    ->setTarif((int)$tarifId)
                    ->setTarifAccessCode($participant['tarif_access_code'] ?? null)
                    ->setJustificatifName($participant['justificatif_name'] ?? null)
                    ->setPlaceNumber($participant['place_number'] ?? null);

                $id = $this->reservationDetailRepository->insert($detail);
                if ($id <= 0) {
                    throw new \RuntimeException('Échec insertion détail.');
                }
            }
        }

        foreach ($tempReservation['reservation_complement'] ?? [] as $tarifId => $complementData) {
            $complement = (new ReservationComplement())
                ->setReservation($newReservationId)
                ->setTarif((int)$tarifId)
                ->setTarifAccessCode($complementData['codes'][0] ?? null)
                ->setQty((int)$complementData['qty']);

            $id = $this->reservationsComplementsRepository->insert($complement);
            if ($id <= 0) {
                throw new \RuntimeException('Échec insertion complément.');
            }
        }
    }

    /**
     * Envoie l'email de confirmation et enregistre l'envoi.
     *
     * @param Reservation $reservation
     * @param string $templateMailCode
     */
    public function sendAndRecordConfirmationEmail(Reservation $reservation, string $templateMailCode): void
    {
        $mailPrepareService = new MailPrepareService();
        if ($mailPrepareService->sendReservationConfirmationEmail($reservation)) {
            $mailTemplateRepository = new MailTemplateRepository();
            $template = $mailTemplateRepository->findByCode('paiement_confirme');
            if ($template) {
                $mailSentRepository = new ReservationMailSentRepository();
                $mailSentRecord = new ReservationMailSent();
                $mailSentRecord->setReservation($reservation->getId())
                    ->setMailTemplate($template->getId())
                    ->setSentAt(date('Y-m-d H:i:s'));
                $id = $mailSentRepository->insert($mailSentRecord);
                if ($id <= 0) {
                    throw new \RuntimeException('Échec insertion mail.');
                }
            }
        } else {
            error_log("Failed to send initial confirmation email for reservation ID: " . $reservation->getId());
        }
    }


    /**
     * Nettoie les données temporaires (NoSQL et MySQL).
     */
    private function cleanupTemporaryData(array $tempReservation): void
    {

        $primaryId = (string) ($tempReservation['_id'] ?? $tempReservation['primary_id']);

        $this->reservationTempWriter->deleteReservation($primaryId);
        $userSessionId = $tempReservation['php_session_id'] ?? session_id();
        if ($userSessionId) {
            $this->reservationPlaceTempRepository->deleteBySession($userSessionId);
        } else {
            error_log("Impossible de nettoyer les places temporaires pour la réservation " . $tempReservation['_id'] . ": php_session_id manquant.");
        }
    }

}
