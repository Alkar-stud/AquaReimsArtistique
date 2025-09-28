<?php

namespace app\Services\DataValidation;

use DateTime;
use Throwable;

class EventPresentationDataValidationService
{
    private ?int $eventId = null;
    private bool $isDisplayed = false;
    private ?DateTime $displayUntil = null;
    private ?string $content = null;

    /**
     * Valide et nettoie les données POST pour la création/édition d'une présentation.
     * @param array $postData Les données de $_POST.
     * @return string|null Un message d'erreur si la validation échoue, sinon null.
     */
    public function checkData(array $postData): ?string
    {
        try {
            // --- Validation de l'événement associé ---
            $eventId = filter_var($postData['event'] ?? 0, FILTER_VALIDATE_INT);
            if ($eventId === false) {
                return "L'événement associé est invalide.";
            }
            $this->eventId = $eventId > 0 ? $eventId : null;

            // --- Validation de la date de fin d'affichage ---
            if (empty($postData['display_until'])) {
                return "La date de fin d'affichage est obligatoire.";
            }
            $this->displayUntil = new DateTime($postData['display_until']);

            // --- Validation du statut d'affichage ---
            $this->isDisplayed = isset($postData['is_displayed']);

            // --- Validation du contenu ---
            // Le contenu peut être vide, mais on le nettoie.
            $this->content = !empty($postData['content']) ? trim($postData['content']) : null;

        } catch (Throwable $e) {
            // Gère les dates invalides qui lèvent une exception dans le constructeur de DateTime
            return "Le format de la date de fin d'affichage est invalide.";
        }

        return null; // Pas d'erreur
    }

    // --- GETTERS ---
    public function getEventId(): ?int { return $this->eventId; }
    public function isDisplayed(): bool { return $this->isDisplayed; }
    public function getDisplayUntil(): ?DateTime { return $this->displayUntil; }
    public function getContent(): ?string { return $this->content; }
}