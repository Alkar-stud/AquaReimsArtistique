<?php
// php
namespace app\Services\Reservation;

use app\DTO\ReservationSelectionSessionDTO;
use app\DTO\ReservationUserDTO;
use app\DTO\ReservationDetailItemDTO;
use app\DTO\ReservationComplementItemDTO;
use InvalidArgumentException;

readonly class ReservationDataPersist
{
    public function __construct(
        private ReservationSessionService $reservationSessionService,
    ) {}

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
            $session = $this->reservationSessionService->getReservationSession() ?? [];
            $complements = $session['reservation_complements'] ?? [];
            $complements[] = $dto->jsonSerialize(); // ou logique de fusion par tarifId si besoin
            $this->reservationSessionService->setReservationSession('reservation_complements', $complements);
            return;
        }

        throw new InvalidArgumentException('Type de DTO non supporté.');
    }

}
