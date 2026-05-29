<?php

namespace app\Services\Calendar;

use app\Models\Event\Event;
use app\Models\Event\EventInscriptionDate;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

class CalendarIcsService
{

    /**
     * Génère un fichier .ics
     *
     * @param Event $event
     * @param EventInscriptionDate $period
     * @return string
     * @throws DateMalformedStringException
     */
    public function buildIcsFile(Event $event, EventInscriptionDate $period): string
    {
        $start = $period->getStartRegistrationAt();
        $end = new DateTimeImmutable($start->format('Y-m-d H:i:s'), $start->getTimezone() ?: new DateTimeZone('Europe/Paris'))->modify('+30 minutes');

        $icsContent = "BEGIN:VCALENDAR\n";
        $icsContent .= "VERSION:2.0\r\n";
        $icsContent .= "PRODID:-//AquaReimsArtistique//FR\r\n";
        $icsContent .= "CALSCALE:GREGORIAN\r\n";
        $icsContent .= "METHOD:PUBLISH\r\n";
        $icsContent .= "BEGIN:VEVENT\r\n";
        $icsContent .= "UID:" . uniqid('', true) . "@aquareimsartistique\r\n";
        $icsContent .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
        $icsContent .= "DTSTART:" . $this->toUtcIcsDate($start) . "\r\n";
        $icsContent .= "DTEND:" . $this->toUtcIcsDate($end) . "\r\n";
        $icsContent .= "SUMMARY:" . $this->escapeIcsText('Ouverture publique des inscriptions - ' . $event->getName()) . "\r\n";
        $icsContent .= "DESCRIPTION:" . $this->escapeIcsText('Ouverture publique des inscriptions.') . "\r\n";
        if ($event->getPiscine()) {
            $location = trim($event->getPiscine()->getLabel() . ' ' . $event->getPiscine()->getAddress());
            $icsContent .= "LOCATION:" . $this->escapeIcsText($location) . "\r\n";
        }
        $icsContent .= "END:VEVENT\r\n";
        $icsContent .= "END:VCALENDAR\r\n";

        return $icsContent;
    }

    private function toUtcIcsDate(DateTimeInterface $date): string
    {
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private function escapeIcsText(string $value): string
    {
        return str_replace(["\\", ";", ",", "\n", "\r"], ["\\\\", "\\;", "\\,", "\\n", ""], $value);
    }



}