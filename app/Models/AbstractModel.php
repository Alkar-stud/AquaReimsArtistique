<?php

namespace app\Models;

use DateMalformedStringException;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

abstract class AbstractModel
{
    protected int $id;
    protected DateTimeInterface $created_at;
    protected ?DateTimeInterface $updated_at = null;


    public function __construct()
    {
        $this->created_at = new DateTime();
        $this->updated_at = null; // S'assure que updated_at est aussi initialisé (même si déjà null par défaut)
    }
    public function getId(): int { return $this->id; }
    public function getCreatedAt(): DateTimeInterface { return $this->created_at; }
    public function getUpdatedAt(): ?DateTimeInterface { return $this->updated_at; }

    public function setId(int $id): static
    {
        $this->id = $id;
        return $this;
    }
    public function setCreatedAt($createdAt): void
    {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $createdAt);
        if ($date === false) {
            throw new InvalidArgumentException("La date fournie est invalide.");
        }
        $this->created_at = $date;
    }

    public function setUpdatedAt($updatedAt): void
    {
        $date = DateTime::createFromFormat('Y-m-d H:i:s', $updatedAt);
        if ($date === false) {
            throw new InvalidArgumentException("La date fournie est invalide.");
        }
        $this->updated_at = $date;
    }
}