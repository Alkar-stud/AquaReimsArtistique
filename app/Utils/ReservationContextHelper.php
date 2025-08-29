<?php
namespace app\Utils;

use app\Repository\Event\EventsRepository;
use app\Repository\Nageuse\NageusesRepository;
use app\Repository\TarifsRepository;

/*
 * Pour afficher le petit encart récapitulatif des réservations tout au long du parcours
 */
class ReservationContextHelper
{
    public static function getContext(
        EventsRepository $eventsRepository,
        TarifsRepository $tarifsRepository,
        ?array $reservation
    ): array {
        $event = $session = $nageuse = null;
        $tarifs = $tarifsById = [];

        if ($reservation && !empty($reservation['event_id'])) {
            $event = $eventsRepository->findById($reservation['event_id']);
            foreach ($event->getSessions() as $s) {
                if ($s->getId() == ($reservation['event_session_id'] ?? null)) {
                    $session = $s;
                    break;
                }
            }
            $tarifs = $tarifsRepository->findByEventId($reservation['event_id']);
            foreach ($tarifs as $tarif) {
                $tarifsById[$tarif->getId()] = $tarif->getLibelle();
            }
        }
        if ($reservation && !empty($reservation['nageuse_id'])) {
            $nageusesRepository = new NageusesRepository();
            $nageuse = $nageusesRepository->findById($reservation['nageuse_id']);
        }

        return [
            'reservation' => $reservation,
            'event' => $event,
            'session' => $session,
            'nageuse' => $nageuse,
            'tarifs' => $tarifs,
            'tarifsById' => $tarifsById
        ];
    }
}