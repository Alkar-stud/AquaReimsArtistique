<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Event\EventInscriptionDates;
use app\Models\Event\Events;
use app\Services\EventsService;

#[Route('/gestion/events', name: 'app_gestion_events')]
class EventsController extends AbstractController
{
    private EventsService $eventsService;

    public function __construct()
    {
        parent::__construct(false);
        $this->eventsService = new EventsService();
    }

    public function index(): void
    {
        $events = $this->eventsService->getUpcomingEvents();
        $piscines = $this->eventsService->getAllPiscines();
        $tarifs = $this->eventsService->getAllTarifs();

        $viewData = [
            'events' => $events,
            'piscines' => $piscines,
            'tarifs' => $tarifs
        ];

        $this->render('/gestion/events', $viewData, 'Gestion des événements');
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

                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Événement ajouté avec succès'];
            } catch (\Exception $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Erreur lors de l\'ajout : ' . $e->getMessage()];
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
                                $inscriptionDates[] = $inscriptionDate;
                            }
                        }
                    }

                    // Sessions
                    $sessions = $_POST['sessions'] ?? [];

                    $this->eventsService->updateEvent($event, $tarifs, $inscriptionDates, $sessions);

                    $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Événement mis à jour'];
                } else {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Événement non trouvé'];
                }
            } catch (\Exception $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Erreur lors de la mise à jour : ' . $e->getMessage()];
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
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Événement supprimé'];
        } catch (\Exception $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'message' => 'Erreur lors de la suppression : ' . $e->getMessage()];
        }

        header('Location: /gestion/events');
        exit;
    }
}