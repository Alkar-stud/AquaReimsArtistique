<?php

namespace app\Services\Reservation;

use app\DTO\ReservationUserDTO;
use app\DTO\ReservationDetailItemDTO;
use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Repository\Reservation\ReservationRepository;
use InvalidArgumentException;
use TypeError;

readonly class ReservationUpdateService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationDetailRepository $reservationDetailRepository
    )
    {
    }

    /**
     * Met à jour un champ de contact d'une réservation en utilisant le DTO pour la validation et la normalisation.
     *
     * @param Reservation $reservation L'objet réservation à mettre à jour.
     * @param string $field Le nom du champ à modifier (ex: 'name', 'email').
     * @param mixed $value La nouvelle valeur.
     * @return bool True si la mise à jour a réussi, false sinon.
     * @throws InvalidArgumentException Si le champ n'est pas valide ou si les données sont incorrectes.
     */
    public function updateContactField(Reservation $reservation, string $field, mixed $value): bool
    {
        // Créer un tableau avec les données actuelles du contact
        $currentData = [
            'name' => $reservation->getName(),
            'firstname' => $reservation->getFirstName(),
            'email' => $reservation->getEmail(),
            'phone' => $reservation->getPhone(),
        ];

        // Remplacer la valeur du champ modifié
        $currentData[$field] = $value;

        // Instancier le DTO avec les données fusionnées pour validation/normalisation
        try {
            $dto = new ReservationUserDTO(...$currentData);
        } catch (TypeError $e) {
            throw new InvalidArgumentException('Données invalides pour la création du DTO.', 0, $e);
        }

        // Récupérer la valeur normalisée depuis le DTO
        $normalizedValue = match ($field) {
            'name' => $dto->name,
            'firstname' => $dto->firstname,
            'email' => $dto->email,
            'phone' => $dto->phone,
            default => throw new InvalidArgumentException("Le champ '$field' n'est pas modifiable."),
        };

        // Mettre à jour le champ unique en base de données
        return $this->reservationRepository->updateSingleField($reservation->getId(), $field, $normalizedValue);
    }

    /**
     * Met à jour un champ d'un participant (détail de réservation).
     *
     * @param int $detailId L'ID du détail de la réservation.
     * @param string $field Le nom du champ à modifier ('name' ou 'firstname').
     * @param mixed $value La nouvelle valeur.
     * @return bool True si la mise à jour a réussi.
     * @throws InvalidArgumentException Si le détail n'est pas trouvé, si le champ n'est pas modifiable ou si les données sont invalides.
     */
    public function updateDetailField(int $detailId, string $field, mixed $value): bool
    {
        // Liste centralisée des champs modifiables pour un participant.
        $updatableFields = [
            'name',
            'firstname',
            // Ajouter ici d'autres champs comme 'justificatif_name'.
        ];

        if (!in_array($field, $updatableFields, true)) {
            throw new InvalidArgumentException("Le champ '$field' n'est pas modifiable pour un participant.");
        }
        $detail = $this->reservationDetailRepository->findById($detailId);
        if (!$detail) {
            throw new InvalidArgumentException("Le participant avec l'ID $detailId n'a pas été trouvé.");
        }

        // Créer un tableau avec les données actuelles du participant
        $currentData = [
            'tarif_id' => $detail->getTarif(), // Requis par le DTO
            'name' => $detail->getName(),
            'firstname' => $detail->getFirstName(),
            'justificatif_name' => $detail->getJustificatifName(),
            'tarif_access_code' => $detail->getTarifAccessCode(),
            'place_id' => is_numeric($detail->getPlaceNumber()) ? (int)$detail->getPlaceNumber() : null,
        ];

        // Remplacer la valeur du champ modifié
        $currentData[$field] = $value;

        // Instancier le DTO pour validation et normalisation
        try {
            // Utilise le constructeur directement, car fromArray n'est pas adapté ici
            $dto = new ReservationDetailItemDTO(...$currentData);
        } catch (TypeError $e) {
            throw new InvalidArgumentException('Données invalides pour la mise à jour du participant.', 0, $e);
        }

        // Récupérer la valeur normalisée depuis le DTO
        $normalizedValue = $dto->{$field};

        return $this->reservationDetailRepository->updateSingleField($detailId, $field, $normalizedValue);
    }

}