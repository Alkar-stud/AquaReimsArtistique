<?php

namespace app\Models;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class Events
{
    private int $id;
    private string $libelle;
    private int $lieu; // ID de la piscine
    private ?Piscines $piscine = null; // Objet Piscines lié
    private DateTimeInterface $opening_doors_at;
    private DateTimeInterface $event_start_at;
    private ?int $limitation_per_swimmer = null;

    private ?int $associate_event = null; // ID de l'événement associé
    private ?self $associatedEvent = null; // Objet Event associé
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;
    private array $tarifs = []; // Collection de tarifs associés à l'événement
    private array $inscriptionDates = []; //Collection des dates d'inscription associées à l'événement



    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function getLieu(): int
    {
        return $this->lieu;
    }

    public function getPiscine(): ?Piscines
    {
        return $this->piscine;
    }

    public function getOpeningDoorsAt(): DateTimeInterface
    {
        return $this->opening_doors_at;
    }

    public function getEventStartAt(): DateTimeInterface
    {
        return $this->event_start_at;
    }

    public function getLimitationPerSwimmer(): ?int
    {
        return $this->limitation_per_swimmer;
    }

    public function getAssociateEvent(): ?int
    {
        return $this->associate_event;
    }

    public function getAssociatedEvent(): ?self
    {
        return $this->associatedEvent;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updated_at;
    }

    /**
     * Récupère tous les tarifs associés à l'événement
     *
     * @return Tarifs[]
     */
    public function getTarifs(): array
    {
        return $this->tarifs;
    }

    /**
     * Récupère toutes les dates d'inscription associées à l'événement
     *
     * @return EventInscriptionDates[]
     */
    public function getInscriptionDates(): array
    {
        return $this->inscriptionDates;
    }


    // --- SETTERS ---

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;
        return $this;
    }

    public function setLieu(int $lieu): self
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function setPiscine(?Piscines $piscine): self
    {
        $this->piscine = $piscine;
        if ($piscine) {
            $this->lieu = $piscine->getId();
        }
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setOpeningDoorsAt(string $opening_doors_at): self
    {
        $this->opening_doors_at = new DateTime($opening_doors_at);
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setEventStartAt(string $event_start_at): self
    {
        $this->event_start_at = new DateTime($event_start_at);
        return $this;
    }

    public function setLimitationPerSwimmer(?int $limitation_per_swimmer): self
    {
        $this->limitation_per_swimmer = $limitation_per_swimmer;
        return $this;
    }

    public function setAssociateEvent(?int $associate_event): self
    {
        $this->associate_event = $associate_event;
        return $this;
    }

    public function setAssociatedEvent(?self $associatedEvent): self
    {
        $this->associatedEvent = $associatedEvent;
        if ($associatedEvent) {
            $this->associate_event = $associatedEvent->getId();
        }
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at ? new DateTime($updated_at) : null;
        return $this;
    }

    /**
     * Définit tous les tarifs associés à l'événement
     *
     * @param Tarifs[] $tarifs
     * @return self
     */
    public function setTarifs(array $tarifs): self
    {
        $this->tarifs = $tarifs;
        return $this;
    }

    /**
     * Ajoute un tarif à l'événement
     *
     * @param Tarifs $tarif
     * @return self
     */
    public function addTarif(Tarifs $tarif): self
    {
        // Vérification que le tarif n'est pas déjà associé (par identifiant)
        foreach ($this->tarifs as $existingTarif) {
            if ($existingTarif->getId() === $tarif->getId()) {
                return $this; // Le tarif existe déjà, on ne fait rien
            }
        }

        $this->tarifs[] = $tarif;
        return $this;
    }

    /**
     * Supprime un tarif de l'événement
     *
     * @param int $tarifId
     * @return self
     */
    public function removeTarif(int $tarifId): self
    {
        foreach ($this->tarifs as $key => $tarif) {
            if ($tarif->getId() === $tarifId) {
                unset($this->tarifs[$key]);
                // Réindexer le tableau
                $this->tarifs = array_values($this->tarifs);
                break;
            }
        }

        return $this;
    }

    /**
     * Vérifie si un tarif est associé à l'événement
     *
     * @param int $tarifId
     * @return bool
     */
    public function hasTarif(int $tarifId): bool
    {
        foreach ($this->tarifs as $tarif) {
            if ($tarif->getId() === $tarifId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Définit toutes les dates d'inscription pour l'événement
     *
     * @param EventInscriptionDates[] $inscriptionDates
     * @return self
     */
    public function setInscriptionDates(array $inscriptionDates): self
    {
        $this->inscriptionDates = $inscriptionDates;
        return $this;
    }

    /**
     * Ajoute une date d'inscription à l'événement
     *
     * @param EventInscriptionDates $inscriptionDate
     * @return self
     */
    public function addInscriptionDate(EventInscriptionDates $inscriptionDate): self
    {
        // Vérifier que la date n'est pas déjà associée
        foreach ($this->inscriptionDates as $existingDate) {
            if ($existingDate->getId() === $inscriptionDate->getId()) {
                return $this;
            }
        }

        $this->inscriptionDates[] = $inscriptionDate;
        // S'assurer que la date référence bien cet événement
        $inscriptionDate->setEventObject($this);
        return $this;
    }

    /**
     * Supprime une date d'inscription
     *
     * @param int $inscriptionDateId
     * @return self
     */
    public function removeInscriptionDate(int $inscriptionDateId): self
    {
        foreach ($this->inscriptionDates as $key => $inscriptionDate) {
            if ($inscriptionDate->getId() === $inscriptionDateId) {
                unset($this->inscriptionDates[$key]);
                $this->inscriptionDates = array_values($this->inscriptionDates);
                break;
            }
        }
        return $this;
    }

}