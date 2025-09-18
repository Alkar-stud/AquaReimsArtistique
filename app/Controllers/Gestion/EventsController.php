<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Event\EventInscriptionDates;
use app\Models\Event\Events;
use app\Services\EventsService;
use app\Services\FlashMessageService;

#[Route('/gestion/events', name: 'app_gestion_events')]
class EventsController extends AbstractController
{
    private EventsService $eventsService;
    private FlashMessageService $flashMessageService;

    public function __construct()
    {
        parent::__construct(false);
        $this->eventsService = new EventsService();
        $this->flashMessageService = new FlashMessageService();
    }

    public function index(): void
    {
        $events = $this->eventsService->getUpcomingEvents();
        $piscines = $this->eventsService->getAllPiscines();
        $tarifs = $this->eventsService->getAllTarifs();

        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        // Préparation des données à envoyer au JS
        $eventsArray = array_map(function($e) {
            $nextDate = null;
            $now = new \DateTime();
            foreach ($e->getInscriptionDates() as $date) {
                if ($date->getStartRegistrationAt() > $now) {
                    if (!$nextDate || $date->getStartRegistrationAt() < $nextDate->getStartRegistrationAt()) {
                        $nextDate = $date;
                    }
                }
            }

            $sessions = $e->getSessions();
            usort($sessions, fn($a,$b) => $a->getEventStartAt() <=> $b->getEventStartAt());

            return [
                'id' => $e->getId(),
                'libelle' => $e->getLibelle(),
                'lieu' => $e->getLieu(),
                'limitation_per_swimmer' => $e->getLimitationPerSwimmer(),
                'nextOpeningDate' => $nextDate ? $nextDate->getStartRegistrationAt()->format('Y-m-d\TH:i') : null,
                'tarifCount' => count($e->getTarifs()),
                'tarifs' => array_map(fn($t) => $t->getId(), $e->getTarifs()),
                'inscription_dates' => array_map(function($d) {
                    return [
                        'id' => $d->getId(),
                        'libelle' => $d->getLibelle(),
                        'start_at' => $d->getStartRegistrationAt()->format('Y-m-d\TH:i'),
                        'close_at' => $d->getCloseRegistrationAt()->format('Y-m-d\TH:i'),
                        'access_code' => $d->getAccessCode()
                    ];
                }, $e->getInscriptionDates()),
                'sessions' => array_map(function($s) {
                    return [
                        'id' => $s->getId(),
                        'session_name' => $s->getSessionName(),
                        'opening_doors_at' => $s->getOpeningDoorsAt()->format('Y-m-d\TH:i'),
                        'event_start_at' => $s->getEventStartAt()->format('Y-m-d\TH:i')
                    ];
                }, $sessions),
            ];
        }, $events);

        $this->render('/gestion/events', [
            'events' => $events,
            'piscines' => $piscines,
            'tarifs' => $tarifs,
            'events_array' => $eventsArray,
            'flash_message' => $flashMessage
        ], 'Gestion des événements');
    }

    #[Route('/gestion/events/add', name: 'app_gestion_events_add')]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $event = new Events();
                $event->setLibelle($_POST['libelle'] ?? '')
                    ->setLieu((int)($_POST['lieu'] ?? 0))
                    ->setLimitationPerSwimmer(
                        ($_POST['limitation_per_swimmer'] == '' || $_POST['limitation_per_swimmer'] == 0
                            ? null
                            : (int)$_POST['limitation_per_swimmer'])
                    )
                    ->setCreatedAt(date('Y-m-d H:i:s'));

                $tarifs = $_POST['tarifs'] ?? [];
                $inscriptionDates = [];
                if (!empty($_POST['inscription_dates']) && is_array($_POST['inscription_dates'])) {
                    foreach ($_POST['inscription_dates'] as $dateData) {
                        if (!empty($dateData['libelle']) && !empty($dateData['start_at']) && !empty($dateData['close_at'])) {
                            $inscriptionDate = new EventInscriptionDates();
                            $inscriptionDate->setLibelle($dateData['libelle'])
                                ->setStartRegistrationAt($dateData['start_at'])
                                ->setCloseRegistrationAt($dateData['close_at'])
                                ->setAccessCode($dateData['access_code'] ?? null);
                            $inscriptionDates[] = $inscriptionDate;
                        }
                    }
                }

                // Sessions
                $sessions = $_POST['sessions'] ?? [];

                $this->eventsService->createEvent($event, $tarifs, $inscriptionDates, $sessions);

                $this->flashMessageService->setFlashMessage('success', "Événement ajouté avec succès");
            } catch (\Exception $e) {
                $this->flashMessageService->setFlashMessage('danger', "Erreur lors de l\'ajout : " . $e->getMessage());
            }

            header('Location: /gestion/events');
            exit;
        }
    }

    #[Route('/gestion/events/update/{id}', name: 'app_gestion_events_update')]
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $event = $this->eventsService->getEventById($id);
                if ($event) {
                    $event->setLibelle($_POST['libelle'] ?? '')
                        ->setLieu((int)($_POST['lieu'] ?? 0))
                        ->setLimitationPerSwimmer(
                            ($_POST['limitation_per_swimmer'] == '' || $_POST['limitation_per_swimmer'] == 0
                                ? null
                                : (int)$_POST['limitation_per_swimmer'])
                        );

                    $tarifs = $_POST['tarifs'] ?? [];
                    $inscriptionDates = [];

                    if (!empty($_POST['inscription_dates']) && is_array($_POST['inscription_dates'])) {
                        foreach ($_POST['inscription_dates'] as $dateData) {
                            if (!empty($dateData['libelle']) && !empty($dateData['start_at']) && !empty($dateData['close_at'])) {
                                $inscriptionDate = new EventInscriptionDates();
                                $inscriptionDate->setLibelle($dateData['libelle'])
                                    ->setStartRegistrationAt($dateData['start_at'])
                                    ->setCloseRegistrationAt($dateData['close_at'])
                                    ->setAccessCode($dateData['access_code'] ?? null);
                                if (isset($dateData['id']) && (int)$dateData['id'] > 0) {
                                    $inscriptionDate->setId((int)$dateData['id']);
                                } else { $inscriptionDate->setId(0); }
                                $inscriptionDates[] = $inscriptionDate;
                            }
                        }
                    }

                    // Sessions
                    $sessions = $_POST['sessions'] ?? [];

                    $this->eventsService->updateEvent($event, $tarifs, $inscriptionDates, $sessions);

                    $this->flashMessageService->setFlashMessage('success', "Événement mis à jour");
                } else {
                    $this->flashMessageService->setFlashMessage('danger', "Événement non trouvé");
                }
            } catch (\Exception $e) {
                $this->flashMessageService->setFlashMessage('danger', "Erreur lors de la mise à jour : " . $e->getMessage());
            }

            header('Location: /gestion/events');
            exit;
        }
    }

    #[Route('/gestion/events/delete/{id}', name: 'app_gestion_events_delete')]
    public function delete(int $id): void
    {
        try {
            $this->eventsService->deleteEvent($id);
            $this->flashMessageService->setFlashMessage('success', "Événement supprimé");
        } catch (\Exception $e) {
                $this->flashMessageService->setFlashMessage('danger', "Erreur lors de la suppression : " . $e->getMessage());
        }

        header('Location: /gestion/events');
        exit;
    }
}