<?php

namespace app\Services\Event;

use app\Models\Event\Event;

class EventPiscineService
{

    public function __construct()
    {
    }

    /**
     * Retourne un tableau de propriétés des piscines par session d'une liste d'évènements.
     *
     * @param Event[] $events
     * @return array
     */
    public function getPiscinesPerEvent(array $events): array
    {
        $piscinesPerEvent = [];
        foreach ($events as $event) {
            foreach ($event->getSessions() as $session) {
                $piscinesPerEvent[$session->getId()]['id'] = $event->getPiscine()->getId();
                $piscinesPerEvent[$session->getId()]['numbered_seats'] = $event->getPiscine()->getNumberedSeats();
                $piscinesPerEvent[$session->getId()]['max_places'] = $event->getPiscine()->getMaxPlaces();
            }
        }
        return $piscinesPerEvent;
    }

}