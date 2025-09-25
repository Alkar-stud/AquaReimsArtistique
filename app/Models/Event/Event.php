<?php

namespace app\Models\Event;

use app\Models\AbstractModel;
use app\Models\Piscine\Piscine;
use app\Models\Tarif\Tarif;

class Event extends AbstractModel
{
    private string $name;
    private int $place; // FK piscine.id
    private ?Piscine $piscine = null;
    private ?int $limitationPerSwimmer = null;

    /** @var EventSession[] */
    private array $sessions = [];
    /** @var Tarif[] */
    private array $tarifs = [];
    /** @var EventInscriptionDate[] */
    private array $inscriptionDates = [];
    /** @var EventPresentations[] */
    private array $presentations = [];

    // --- GETTERS ---
    public function getName(): string { return $this->name; }
    public function getPlace(): int { return $this->place; }
    public function getPiscine(): ?Piscine { return $this->piscine; }
    public function getLimitationPerSwimmer(): ?int { return $this->limitationPerSwimmer; }
    public function getSessions(): array { return $this->sessions; }
    public function getTarifs(): array { return $this->tarifs; }
    public function getInscriptionDates(): array { return $this->inscriptionDates; }
    public function getPresentations(): array { return $this->presentations; }

    // --- SETTERS ---
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function setPlace(int $place): self { $this->place = $place; return $this; }
    public function setPiscine(?Piscine $piscine): self {
        $this->piscine = $piscine;
        if ($piscine) { $this->place = $piscine->getId(); }
        return $this;
    }
    public function setLimitationPerSwimmer(?int $limitationPerSwimmer): self {
        $this->limitationPerSwimmer = $limitationPerSwimmer; return $this;
    }

    public function setSessions(array $sessions): self { $this->sessions = $sessions; return $this; }
    public function addSession(EventSession $session): self {
        foreach ($this->sessions as $s) { if ($s->getId() === $session->getId()) { return $this; } }
        $this->sessions[] = $session; return $this;
    }

    public function setTarifs(array $tarifs): self { $this->tarifs = $tarifs; return $this; }
    public function addTarif(Tarif $tarif): self {
        foreach ($this->tarifs as $t) { if ($t->getId() === $tarif->getId()) { return $this; } }
        $this->tarifs[] = $tarif; return $this;
    }

    public function setInscriptionDates(array $dates): self { $this->inscriptionDates = $dates; return $this; }
    public function addInscriptionDate(EventInscriptionDate $date): self {
        foreach ($this->inscriptionDates as $d) { if ($d->getId() === $date->getId()) { return $this; } }
        $this->inscriptionDates[] = $date; return $this;
    }

    public function setPresentations(array $presentations): self { $this->presentations = $presentations; return $this; }
    public function addPresentation(EventPresentations $presentation): self {
        foreach ($this->presentations as $p) { if ($p->getId() === $presentation->getId()) { return $this; } }
        $this->presentations[] = $presentation; return $this;
    }
}
