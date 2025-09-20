<?php

namespace app\Models\Event;

use app\Models\Piscine\Piscines;
use app\Models\Tarif;
use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class Events
{
    private int $id;
    private string $libelle;
    private int $lieu; // ID de la piscine
    private ?Piscines $piscine = null; // Objet Piscines lié
    private ?int $limitation_per_swimmer = null;
    private DateTimeInterface $created_at;
    private ?DateTimeInterface $updated_at = null;
    private array $tarifs = []; // Collection de tarifs associés à l'événement
    private array $inscriptionDates = []; // Collection des dates d'inscription associées à l'événement
    private array $sessions = []; // Collection des sessions associées à l'événement

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

    public function getLimitationPerSwimmer(): ?int
    {
        return $this->limitation_per_swimmer;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updated_at;
    }

    public function getTarifs(): array
    {
        return $this->tarifs;
    }

    public function getInscriptionDates(): array
    {
        return $this->inscriptionDates;
    }

    public function getSessions(): array
    {
        return $this->sessions;
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

    public function setLimitationPerSwimmer(?int $limitation_per_swimmer): self
    {
        $this->limitation_per_swimmer = $limitation_per_swimmer;
        return $this;
    }

    public function setCreatedAt(string $created_at): self
    {
        $this->created_at = new DateTime($created_at);
        return $this;
    }

    public function setUpdatedAt(?string $updated_at): self
    {
        $this->updated_at = $updated_at ? new DateTime($updated_at) : null;
        return $this;
    }

    public function setTarifs(array $tarifs): self
    {
        $this->tarifs = $tarifs;
        return $this;
    }

    public function setInscriptionDates(array $inscriptionDates): self
    {
        $this->inscriptionDates = $inscriptionDates;
        return $this;
    }

    public function setSessions(array $sessions): self
    {
        $this->sessions = $sessions;
        return $this;
    }

    public function addSession(EventSession $session): self
    {
        foreach ($this->sessions as $existingSession) {
            if ($existingSession->getId() === $session->getId()) {
                return $this;
            }
        }
        $this->sessions[] = $session;
        return $this;
    }

    // Méthodes utilitaires pour les tarifs et dates d'inscription (conservées)
    public function addTarif(Tarif $tarif): self
    {
        foreach ($this->tarifs as $existingTarif) {
            if ($existingTarif->getId() === $tarif->getId()) {
                return $this;
            }
        }
        $this->tarifs[] = $tarif;
        return $this;
    }

    public function addInscriptionDate(EventInscriptionDates $inscriptionDate): self
    {
        foreach ($this->inscriptionDates as $existingDate) {
            if ($existingDate->getId() === $inscriptionDate->getId()) {
                return $this;
            }
        }
        $this->inscriptionDates[] = $inscriptionDate;
        $inscriptionDate->setEventObject($this);
        return $this;
    }
}