<?php

namespace app\Services\Calendar;

use app\Models\Event\Event;
use app\Models\Event\EventInscriptionDate;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use app\Utils\BuildLink;
use app\Utils\DeviceDetector;

class CalendarLinkService
{

    /**
     * Retourne tous les liens de calendrier utiles pour une ouverture d'inscription.
     *
     * @param Event $event
     * @param EventInscriptionDate $period
     * @return array{google:string,outlook:string,yahoo:string,apple:string,ics:string}
     */
    public function buildCalendarLinks(Event $event, EventInscriptionDate $period): array
    {
        return [
            'google' => $this->buildGoogleCalendarUrl($event, $period),
            'outlook' => $this->buildOutlookCalendarUrl($event, $period),
            'yahoo' => $this->buildYahooCalendarUrl($event, $period),
            'apple' => $this->buildAppleCalendarUrl($event),
            'ics' => $this->buildIcsDownloadUrl($event),
        ];
    }

    /**
     * Retourne les liens calendrier adaptés au device (mobile/desktop)
     *
     * @param Event $event
     * @param EventInscriptionDate $period
     * @return array<string, array{label:string,href:string,priority:int}> Liens indexés par clé fournisseur
     */
    public function buildAdaptiveCalendarLinks(Event $event, EventInscriptionDate $period): array
    {
        $isMobile = DeviceDetector::isMobileOrTablet();
        $isApple = DeviceDetector::isAppleDevice();

        $links = [
            'google' => [
                'label' => 'Google',
                'href' => $this->buildGoogleCalendarUrl($event, $period),
                'priority' => $isMobile ? 2 : 1,
            ],
            'outlook' => [
                'label' => 'Outlook',
                'href' => $this->buildOutlookCalendarUrl($event, $period),
                'priority' => $isMobile ? 3 : 2,
            ],
            'yahoo' => [
                'label' => 'Yahoo',
                'href' => $this->buildYahooCalendarUrl($event, $period),
                'priority' => 4,
            ],
            'apple' => [
                'label' => 'Apple',
                'href' => $this->buildAppleCalendarUrl($event),
                'priority' => $isApple && $isMobile ? 1 : 3,
            ],
            'ics' => [
                'label' => 'Télécharger .ics',
                'href' => $this->buildIcsDownloadUrl($event),
                'priority' => $isMobile ? 5 : 4,
            ],
        ];

        // Trier par priorité (les moins prioritaires en dernier)
        uasort($links, fn($a, $b) => $a['priority'] <=> $b['priority']);

        return $links;
    }

    public function buildGoogleCalendarUrl(Event $event, EventInscriptionDate $period): string
    {
        $start = $period->getStartRegistrationAt();
        $end = $this->buildEndDateTime($start);

        $params = [
            'action' => 'TEMPLATE',
            'text' => 'Ouverture publique des inscriptions pour ' . $event->getName(),
            'dates' => $this->formatGoogleDates($start, $end),
            'details' => 'Ouverture publique des inscriptions.',
        ];

        if ($event->getPiscine()) {
            $params['location'] = trim($event->getPiscine()->getLabel() . ' ' . $event->getPiscine()->getAddress());
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    public function buildOutlookCalendarUrl(Event $event, EventInscriptionDate $period): string
    {
        $start = $period->getStartRegistrationAt();
        $end = $this->buildEndDateTime($start);

        $params = [
            'path' => '/calendar/action/compose',
            'rru' => 'addevent',
            'subject' => 'Ouverture publique des inscriptions pour ' . $event->getName(),
            'body' => 'Ouverture publique des inscriptions.',
            'startdt' => $this->formatDateTimeForUrl($start),
            'enddt' => $this->formatDateTimeForUrl($end),
        ];

        if ($event->getPiscine()) {
            $params['location'] = trim($event->getPiscine()->getLabel() . ' ' . $event->getPiscine()->getAddress());
        }

        return 'https://outlook.office.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }

    public function buildYahooCalendarUrl(Event $event, EventInscriptionDate $period): string
    {
        $start = $period->getStartRegistrationAt();
        $end = $this->buildEndDateTime($start);

        $params = [
            'v' => 60,
            'title' => 'Ouverture publique des inscriptions pour ' . $event->getName(),
            'st' => $this->formatYahooDateTime($start),
            'et' => $this->formatYahooDateTime($end),
            'desc' => 'Ouverture publique des inscriptions.',
        ];

        if ($event->getPiscine()) {
            $params['in_loc'] = trim($event->getPiscine()->getLabel() . ' ' . $event->getPiscine()->getAddress());
        }

        return 'https://calendar.yahoo.com/?' . http_build_query($params);
    }

    public function buildIcsDownloadUrl(Event $event): string
    {
        return BuildLink::buildBasicLink('/reservation/' . $event->getId() . '/ics');
    }

    public function buildAppleCalendarUrl(Event $event): string
    {
        return str_replace(['https://', 'http://'], 'webcal://', $this->buildIcsDownloadUrl($event));
    }

    private function buildEndDateTime(DateTimeInterface $start): DateTimeInterface
    {
        return (new DateTimeImmutable($start->format('Y-m-d H:i:s'), $start->getTimezone() ?: new DateTimeZone('Europe/Paris')))
            ->modify('+30 minutes');
    }

    private function formatGoogleDates(DateTimeInterface $start, DateTimeInterface $end): string
    {
        return $this->formatGoogleDateTime($start) . '/' . $this->formatGoogleDateTime($end);
    }

    private function formatGoogleDateTime(DateTimeInterface $date): string
    {
        return (new DateTimeImmutable($date->format('Y-m-d H:i:s'), $date->getTimezone() ?: new DateTimeZone('Europe/Paris')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    }

    private function formatDateTimeForUrl(DateTimeInterface $date): string
    {
        return $date->format(DateTimeInterface::ATOM);
    }

    private function formatYahooDateTime(DateTimeInterface $date): string
    {
        return (new DateTimeImmutable($date->format('Y-m-d H:i:s'), $date->getTimezone() ?: new DateTimeZone('Europe/Paris')))
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Ymd\THis\Z');
    }


}