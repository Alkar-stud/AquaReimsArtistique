<?php

namespace app\Services\Reservation;

use app\DTO\ReservationUserDTO;
use app\Models\Reservation\Reservation;
use app\Repository\Reservation\ReservationRepository;
use InvalidArgumentException;
use TypeError;

readonly class ReservationUpdateService
{
    public function __construct(private ReservationRepository $reservationRepository)
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
}