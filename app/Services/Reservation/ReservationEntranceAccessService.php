<?php

namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Models\User\User;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Log\Logger;
use DateTime;

class ReservationEntranceAccessService
{
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;
    private ReservationDetailRepository $reservationDetailRepository;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationQueryService $reservationQueryService,
        ReservationDetailRepository $reservationDetailRepository,
    )
    {
        $this->reservationRepository = $reservationRepository;
        $this->reservationQueryService = $reservationQueryService;
        $this->reservationDetailRepository = $reservationDetailRepository;
    }

    /**
     * @param Reservation $reservation
     * @return array|true[]
     */
    public function canModifyReservation(Reservation $reservation): array
    {
        $eventStart = $this->getEventStartDateTime($reservation);
        if ($eventStart === null) {
            return ['allowed' => false, 'message' => 'Session non trouvée.'];
        }

        $availableAt = $this->getModificationAvailableAt($eventStart);
        $now = new DateTime();

        if (!$this->isModificationAllowed($now, $availableAt)) {
            return $this->buildAccessDeniedResponse($availableAt);
        }

        //On compare le nombre de détails avec entered_at == null au nombre de details avec entered_at == not null
        $everyOneInReservation = $this->reservationQueryService->everyOneInReservationIsHere($reservation);

        return ['allowed' => true, 'everyOneInReservation' => $everyOneInReservation];
    }

    /**
     * Donne la date et l'heure de début de l'événement à partir d'une réservation.
     *
     * @param Reservation $reservation
     * @return DateTime|null L'objet DateTime de début de l'événement, ou null s'il est introuvable.
     */
    private function getEventStartDateTime(Reservation $reservation): ?DateTime
    {
        $eventSession = $reservation->getEventSessionObject();
        if (!$eventSession) {
            return null;
        }

        return DateTime::createFromInterface($eventSession->getOpeningDoorsAt());
    }

    /**
     * Calcul du moment où les modifications sont disponibles.
     *
     * @param DateTime $eventStart L'heure de début de l'événement.
     * @return DateTime L'objet DateTime représentant la période pendant laquelle les modifications sont disponibles.
     */
    private function getModificationAvailableAt(DateTime $eventStart): DateTime
    {
        return (clone $eventStart)->modify('-2 hours');
    }

    /**
     * Vérification si la modification est autorisée en fonction de l'heure actuelle et du temps disponible.
     *
     * @param DateTime $now
     * @param DateTime $availableAt L'objet DateTime lorsque des modifications sont disponibles.
     * @return bool True Si la modification est autorisée, false sinon.
     */
    private function isModificationAllowed(DateTime $now, DateTime $availableAt): bool
    {
        return $now >= $availableAt;
    }

    /**
     * Construit un tableau de réponses d'accès refusé.
     *
     * @param DateTime $availableAt L'objet DateTime lorsque des modifications sont disponibles.
     * @return array Un tableau associatif contenant des informations de refus d'accès.
     */
    private function buildAccessDeniedResponse(DateTime $availableAt): array
    {
        return [
            'allowed' => false,
            'message' => 'Les modifications ne sont pas encore autorisées. Accessible 2h avant l\'ouverture des portes.',
            'availableAt' => $availableAt->format('d/m/Y à H:i'),
        ];
    }

    /**
     * Pour vérifier les compléments
     *
     * @param Reservation $reservation
     * @param bool $complement
     * @param User $currentUser
     * @return array
     */
    public function checkComplementForEntrance(Reservation $reservation, bool $complement, User $currentUser): array
    {
        $value = $complement ? date('Y-m-d H:i:s') : null;
        $this->reservationRepository->updateSingleField($reservation->getId(), 'complements_given_at', $value);
        $this->reservationRepository->updateSingleField($reservation->getId(), 'complements_given_by', $value == null ? null:$currentUser->getId());
        //On log l'event
        Logger::get()->event(
            'reservation.complement.entrance.checked',
            [
                'reservation_id' => $reservation->getId(),
                'complements_given_by_user_id' => $currentUser->getId(),
                'value' => $complement
            ]);
        $userName = $value !== null ? $currentUser->getDisplayName() : null;

        return $this->buildUpdateComplementResponse($value, $userName);
    }

    /**
     * Construit un tableau de réponse d'ajout de vérification de complément
     *
     * @param string|null $value
     * @param string|null $userName
     * @return array Un tableau associatif contenant des informations.
     */
    private function buildUpdateComplementResponse(?string $value, ?string $userName): array
    {
        return [
            'success' => true,
            'message' => 'Mise à jour effectuée',
            'complements_given_at' => $value,
            'user_name' => $userName
        ];
    }

    /**
     * Pour vérifier les participants
     *
     * @param Reservation $reservation
     * @param int $participant
     * @param User $currentUser
     * @param bool $isPresent
     * @return array
     */
    public function checkParticipantForEntrance(Reservation $reservation, int $participant, User $currentUser, bool $isPresent): array
    {
        $detail = $this->reservationDetailRepository->findById($participant, false, false, false);
        if (!$detail || $detail->getReservation() !== $reservation->getId()) {
            return [
                'success' => false,
                'message' => 'Participant invalide ou ne correspondant pas à cette réservation.',
            ];
        }

        $value = $isPresent ? date('Y-m-d H:i:s') : null;
        $this->reservationDetailRepository->updateSingleField($participant, 'entered_at', $value);
        $this->reservationDetailRepository->updateSingleField($participant, 'entry_validate_by', $value == null ? null:$currentUser->getId());
        //On compare le nombre de détails avec entered_at == null au nombre de details avec entered_at == not null
        $everyOneInReservation = $this->reservationQueryService->everyOneInReservationIsHere($reservation);

        //On log l'event
        Logger::get()->event(
            'reservation.detail.entrance.attendance_marked',
            [
                'reservation_id' => $reservation->getId(),
                'entry_validate_by_user_id' => $currentUser->getId(),
                'participant_id' => $participant,
                'value' => $isPresent
            ]);

        $userName = $value !== null ? $currentUser->getDisplayName() : null;

        return $this->buildUpdateParticipantResponse($everyOneInReservation, $userName);
    }

    /**
     * Construit un tableau de réponse d'ajout de vérification de complément
     *
     * @param bool $everyOneInReservation
     * @param string|null $userName
     * @return array Un tableau associatif contenant des informations.
     */
    private function buildUpdateParticipantResponse(bool $everyOneInReservation, ?string $userName): array
    {
        return [
            'success' => true,
            'message' => 'Mise à jour effectuée',
            'everyOneInReservation' => $everyOneInReservation,
            'user_name' => $userName
        ];
    }

}