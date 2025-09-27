<?php

namespace app\Services\Event;

/**
 * Représente le résultat d'une opération à propos d'un événement.
 * Permet de communiquer un état détaillé (succès total, partiel) du service au contrôleur.
 * Pas besoin si pas de possibilité de réussite "partielle".
 */
class EventResult
{
    public const string SUCCESS = 'success';
    public const string PARTIAL_SUCCESS = 'warning';

    private string $status;
    private string $eventName;
    private array $undeletableSessionNames;

    public function __construct(string $status, string $eventName, array $undeletableSessionNames = [])
    {
        $this->status = $status;
        $this->eventName = $eventName;
        $this->undeletableSessionNames = $undeletableSessionNames;
    }

    public function getStatus(): string { return $this->status; }
    public function getEventName(): string { return $this->eventName; }
    public function getUndeletableSessionNames(): array { return $this->undeletableSessionNames; }
}