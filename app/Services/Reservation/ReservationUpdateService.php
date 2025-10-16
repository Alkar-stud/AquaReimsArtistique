<?php

namespace app\Services\Reservation;

use app\Core\Database;
use app\DTO\ReservationUserDTO;
use app\DTO\ReservationDetailItemDTO;
use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationComplement;
use app\Repository\Reservation\ReservationComplementRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Repository\Tarif\TarifRepository;
use app\Repository\Reservation\ReservationDetailRepository;
use app\Services\Log\Logger;
use app\Services\Mails\MailService;
use app\Services\Mails\MailPrepareService;
use InvalidArgumentException;
use Throwable;
use TypeError;

readonly class ReservationUpdateService
{
    public function __construct(
        private ReservationRepository $reservationRepository,
        private ReservationDetailRepository $reservationDetailRepository,
        private ReservationComplementRepository $reservationComplementRepository,
        private TarifRepository $tarifRepository,
        private ReservationPriceCalculator $priceCalculator,
        private MailService $mailService,
        private MailPrepareService $mailPrepareService,
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

    /**
     * Pour mettre à jour une ligne de complément
     * @param int $reservationId
     * @param int $complementId
     * @param string $action
     * @return bool True si la mise à jour a réussi.
     * @throws InvalidArgumentException Si le complément n'est pas trouvé.
     */
    public function updateComplementQuantity(int $reservationId, int $complementId, string $action): bool
    {
        $complement = $this->reservationComplementRepository->findById($complementId);
        if (!$complement) {
            throw new InvalidArgumentException('Complément non trouvé.');
        }

        $qty = $complement->getQty();
        if ($action === 'plus') {
            $qty++;
        } else {
            $qty--;
        }
        $complement->setQty($qty);

        if ($qty <= 0) {
            $success = $this->reservationComplementRepository->delete($complement->getId());
        } else {
            $success = $this->reservationComplementRepository->update($complement);
        }

        if ($success) {
            $this->recalculateAndSaveTotal($reservationId);
        }

        return $success;
    }

    /**
     * Pour ajouter un complément à une réservation
     * @param int $reservationId
     * @param int $tarifId
     * @return bool True si l'ajout ou la mise à jour a réussi.
     */
    public function addComplement(int $reservationId, int $tarifId): bool
    {
        // On vérifie si un complément avec ce tarif existe déjà pour cette réservation
        $existing = $this->reservationComplementRepository->findByReservationAndTarif($reservationId, $tarifId);


        // Si oui, on incrémente simplement sa quantité en réutilisant la méthode existante
        if ($existing) {
            return $this->updateComplementQuantity($reservationId, $existing->getId(), 'plus');
        }

        // Sinon, on en crée un nouveau
        $newComplement = new ReservationComplement();
        $newComplement->setReservation($reservationId)
            ->setTarif($tarifId)
            ->setQty(1);

        $newId = $this->reservationComplementRepository->insert($newComplement);

        if ($newId > 0) {
            $this->recalculateAndSaveTotal($reservationId);
            return true;
        }

        return false;
    }


    /**
     * Pour annuler une réservation et supprimer/modifier les éléments de la commande
     *
     * @param Reservation $reservation
     * @return bool|null
     */
    public function cancelReservation(Reservation $reservation): bool|string
    {
        $pdo = Database::getInstance();

        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            //On tague la commande comme annulée
            if (!$this->reservationRepository->updateSingleField($reservation->getId(), 'is_canceled', true)) {
                throw new \RuntimeException('Échec de la mise à jour du statut d\'annulation de la réservation.');
            }

            //On supprime les éventuelles places numérotées de la commande
            // TODO: The following line is incorrect. ReservationDetailRepository::updateSingleField expects a detail ID, not a reservation ID.
            // A new method like `clearPlaceNumbersForReservation(int $reservationId)` should be added to ReservationDetailRepository
            // if the intent is to clear place numbers for all details associated with this reservation.
            // For now, this line is commented out to prevent a fatal error.
            /*
            if (!$this->reservationDetailRepository->updateSingleField($reservation->getId(), 'place_number', null)) {
                throw new \RuntimeException('Erreur lors de la suppression des places numérotées.');
            }
            */

            // Envoyer l'email de confirmation d'annulation
            if (!$this->mailPrepareService->sendCancelReservationConfirmationEmail($reservation)) {
                throw new \RuntimeException('Échec de l\'envoi de l\'email d\'annulation.');
            }

            // Enregistrer l'envoi de l'email
            if (!$this->mailService->recordMailSent($reservation, 'cancel_order')) {
                throw new \RuntimeException('Échec de l\'enregistrement de l\'envoi de l\'email d\'annulation.');
            }

            // Commit si tout est OK
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::get()->error('Cancel order', $e->getMessage(), ['Erreur lors de l\'annulation de la commande ' . $reservation->getId()]);
            return false;
        }
    }
    /**
     * Pour recalculer le total à payer de la réservation
     * @param int $reservationId
     * @return void
     */
    private function recalculateAndSaveTotal(int $reservationId): void
    {
        $reservation = $this->reservationRepository->findById($reservationId, false, false, false, true);
        if (!$reservation) {
            return; // Ne rien faire si la réservation n'existe pas
        }

        // Récupérer toutes les données nécessaires
        $allEventTarifs = $this->tarifRepository->findByEventId($reservation->getEvent());
        $allReservationDetails = $this->reservationDetailRepository->findByReservation($reservationId);
        $allReservationComplements = $this->reservationComplementRepository->findByReservation($reservationId);

        // Créer un lookup-map pour les tarifs par ID pour un accès rapide
        $tarifsById = [];
        foreach ($allEventTarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }

        // Calculer le sous-total des détails (participants)
        $detailsSubtotal = 0;
        $detailsGroupedByTarif = [];
        foreach ($allReservationDetails as $detail) {
            $detailsGroupedByTarif[$detail->getTarif()][] = $detail;
        }
        foreach ($detailsGroupedByTarif as $tarifId => $participants) {
            $tarif = $tarifsById[$tarifId] ?? null;
            if ($tarif) {
                $calc = $this->priceCalculator->computeDetailTotals(count($participants), (int)$tarif->getSeatCount(), $tarif->getPrice());
                $detailsSubtotal += $calc['total'];
            }
        }

        // Calculer le sous-total des compléments
        $complementsSubtotal = 0;
        foreach ($allReservationComplements as $complement) {
            $tarif = $tarifsById[$complement->getTarif()] ?? null;
            if ($tarif) {
                $complementsSubtotal += $this->priceCalculator->computeComplementTotal($complement->getQty(), $tarif->getPrice());
            }
        }

        // Sauvegarder le nouveau total
        $newTotalAmount = $detailsSubtotal + $complementsSubtotal;
        $this->reservationRepository->updateSingleField($reservationId, 'total_amount', (string)$newTotalAmount);
    }

}