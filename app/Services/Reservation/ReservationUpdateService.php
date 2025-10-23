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
     * Pour gérer les mises à jour des différents éléments d'une réservation
     *
     * @param Reservation $reservation
     * @param string $typeField
     * @param int|null $fieldId
     * @param int|null $tarifId
     * @param string|null $field
     * @param mixed $value
     * @param string|null $action
     * @return array
     */
    public function handleUpdateReservationFields(Reservation $reservation, string $typeField, ?int $fieldId, ?int $tarifId, ?string $field, mixed $value, ?string $action): array
    {
        if ($typeField == 'contact') {
            try {
                $success = $this->updateContactField($reservation, $field, $value);
                $return = [
                    'success' => $success,
                    'message' => $success ? 'Mise à jour réussie.' : 'La mise à jour a échoué.'
                ];
            } catch (InvalidArgumentException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
        } elseif ($typeField == 'detail') {
            try {
                $success = $this->updateDetailField((int)$fieldId, $field, $value);
                $return = [
                    'success' => $success,
                    'message' => $success ? 'Mise à jour réussie.' : 'La mise à jour a échoué.'
                ];
            } catch (InvalidArgumentException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
        } elseif ($typeField == 'complement') {
            try {
                if ($fieldId) { // Mise à jour d'un complément existant
                    $return = $this->updateComplementQuantity($reservation->getId(), $fieldId, $action);
                } elseif ($tarifId) { // Ajout d'un nouveau complément
                    $return = $this->addComplement($reservation->getId(), (int)$tarifId);
                } else {
                    $return = ['success' => false, 'message' => 'Action sur complément non valide.'];
                }
            } catch (InvalidArgumentException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
            //On met la commande à 'non vérifiée'
            $this->reservationRepository->updateSingleField($reservation->getId(), 'is_checked', false);

        } elseif ($typeField == 'cancel') {
            $success = $this->cancelReservation($reservation);
            $return = [
                'success' => $success,
                'message' => $success ? 'Commande annulée.' : 'Erreur lors de l\'annulation.',
                'reload' => $success
            ];
        } else {
            $return = ['success' => false, 'message' => 'La mise à jour a échoué.'];
        }

        return $return;
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
     * @return array
     */
    public function updateComplementQuantity(int $reservationId, int $complementId, string $action): array
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
            $message = $success ? 'Complément supprimé avec succès.' : 'Échec de la suppression du complément.';
        } else {
            $success = $this->reservationComplementRepository->update($complement);
            $message = $success ? 'Quantité du complément mise à jour.' : 'Échec de la mise à jour de la quantité du complément.';
        }

        if ($success) {
            $this->recalculateAndSaveTotal($reservationId);

            // Récupérer la réservation mise à jour pour obtenir les nouveaux totaux
            $updatedReservation = $this->reservationRepository->findById($reservationId, false, false, false, true);
            if (!$updatedReservation) {
                return ['success' => false, 'message' => 'Réservation introuvable après mise à jour des totaux.'];
            }

            // Récupérer le tarif pour calculer le total du groupe de compléments
            $tarif = $this->tarifRepository->findById($complement->getTarif());
            $groupTotalCents = $tarif ? $this->priceCalculator->computeComplementTotal($qty, $tarif->getPrice()) : 0;

            return [
                'success' => true,
                'message' => $message,
                'newQuantity' => $qty,
                'groupTotalCents' => $groupTotalCents,
                'totals' => [
                    'totalAmount' => $updatedReservation->getTotalAmount(),
                    'totalPaid' => $updatedReservation->getTotalAmountPaid(),
                    'amountDue' => $updatedReservation->getTotalAmount() - $updatedReservation->getTotalAmountPaid(),
                ]
            ];
        }

        return ['success' => false, 'message' => $message];
    }

    /**
     * Pour ajouter un complément à une réservation
     * @param int $reservationId
     * @param int $tarifId
     * @return array True si l'ajout ou la mise à jour a réussi.
     */
    public function addComplement(int $reservationId, int $tarifId): array
    {
        // On vérifie si un complément avec ce tarif existe déjà pour cette réservation
        $existing = $this->reservationComplementRepository->findByReservationAndTarif($reservationId, $tarifId);

        // Si oui, on incrémente simplement sa quantité en réutilisant la méthode existante
        if ($existing) {
            // updateComplementQuantity renvoie le tableau complet
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

            // Récupérer la réservation mise à jour pour obtenir les nouveaux totaux
            $updatedReservation = $this->reservationRepository->findById($reservationId, false, false, false, true);
            if (!$updatedReservation) {
                return ['success' => false, 'message' => 'Réservation introuvable après ajout de complément.'];
            }

            // Récupérer le tarif pour calculer le total du groupe de compléments (quantité 1 pour un nouvel ajout)
            $tarif = $this->tarifRepository->findById($tarifId);
            $groupTotalCents = $tarif ? $this->priceCalculator->computeComplementTotal(1, $tarif->getPrice()) : 0;

            return [
                'success' => true,
                'message' => 'Complément ajouté avec succès.',
                'id' => $newId, // L'ID du nouveau complément créé
                'newQuantity' => 1,
                'groupTotalCents' => $groupTotalCents,
                'totals' => [
                    'totalAmount' => $updatedReservation->getTotalAmount(),
                    'totalPaid' => $updatedReservation->getTotalAmountPaid(),
                    'amountDue' => $updatedReservation->getTotalAmount() - $updatedReservation->getTotalAmountPaid(),
                ]
            ];
        }

        return ['success' => false, 'id' => 0, 'message' => 'L\'ajout du complément a échoué.'];
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
            if (!$this->reservationDetailRepository->updateSingleField($reservation->getId(), 'place_number', null)) {
                throw new \RuntimeException('Erreur lors de la suppression des places numérotées.');
            }

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