<?php

namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\EventsRepository;
use app\Repository\PiscinesRepository;
use app\Repository\TarifsRepository;
use app\Repository\EventInscriptionDatesRepository;
use app\Models\Events;
use app\Models\EventInscriptionDates;

#[Route('/gestion/events', name: 'app_gestion_events')]
class EventsController extends AbstractController
{
    private EventsRepository $repository;
    private PiscinesRepository $piscinesRepository;
    private TarifsRepository $tarifsRepository;
    private EventInscriptionDatesRepository $inscriptionDatesRepository;

    public function __construct() {
        parent::__construct(false);
        $this->piscinesRepository = new PiscinesRepository();
        $this->tarifsRepository = new TarifsRepository();
        $this->inscriptionDatesRepository = new EventInscriptionDatesRepository();
        $this->repository = new EventsRepository(
            $this->piscinesRepository,
            $this->tarifsRepository,
            $this->inscriptionDatesRepository
        );
    }

    public function index(): void
    {
        $events = $this->repository->findSortByDate(true);
        $piscines = $this->piscinesRepository->findAll();
        $tarifs = $this->tarifsRepository->findAll('all', true);

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
            $event = new Events();
            $event->setLibelle($_POST['libelle'] ?? '')
                ->setLieu((int)($_POST['lieu'] ?? 0))
                ->setOpeningDoorsAt($_POST['opening_doors_at'] ?? '')
                ->setEventStartAt($_POST['event_start_at'] ?? '')
                ->setLimitationPerSwimmer(($_POST['limitation_per_swimmer'] == '' || $_POST['limitation_per_swimmer'] == 0 ? null : (int)$_POST['limitation_per_swimmer']))
                ->setAssociateEvent(!empty($_POST['associate_event']) ? (int)$_POST['associate_event'] : null);

            // Traitement des tarifs associés AVANT l'insertion
            if (!empty($_POST['tarifs']) && is_array($_POST['tarifs'])) {
                foreach ($_POST['tarifs'] as $tarifId) {
                    $tarif = $this->tarifsRepository->findById((int)$tarifId);
                    if ($tarif) {
                        $event->addTarif($tarif);
                    }
                }
            }

            // Insérer l'événement et récupérer son ID
            $eventId = $this->repository->insert($event);

            // Traitement des dates d'inscription
            if (!empty($_POST['inscription_dates']) && is_array($_POST['inscription_dates'])) {
                foreach ($_POST['inscription_dates'] as $dateData) {
                    if (!empty($dateData['libelle']) && !empty($dateData['start_at']) && !empty($dateData['close_at'])) {
                        $inscriptionDate = new EventInscriptionDates();
                        $inscriptionDate->setEvent($eventId)
                            ->setLibelle($dateData['libelle'])
                            ->setStartRegistrationAt($dateData['start_at'])
                            ->setCloseRegistrationAt($dateData['close_at'])
                            ->setAccessCode($dateData['access_code'] ?? null);

                        $this->inscriptionDatesRepository->insert($inscriptionDate);
                    }
                }
            }

            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Événement ajouté avec succès'];
            header('Location: /gestion/events');
            exit;
        }
    }

    #[Route('/gestion/events/update/{id}', name: 'app_gestion_events_update')]
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $event = $this->repository->findById($id);
            if ($event) {
                $event->setLibelle($_POST['libelle'] ?? '')
                    ->setLieu((int)($_POST['lieu'] ?? 0))
                    ->setOpeningDoorsAt($_POST['opening_doors_at'] ?? '')
                    ->setEventStartAt($_POST['event_start_at'] ?? '')
                    ->setLimitationPerSwimmer(($_POST['limitation_per_swimmer'] == '' || $_POST['limitation_per_swimmer'] == 0 ? null : (int)$_POST['limitation_per_swimmer']))
                    ->setAssociateEvent(!empty($_POST['associate_event']) ? (int)$_POST['associate_event'] : null);

                // Mise à jour des tarifs
                $event->setTarifs([]);
                if (!empty($_POST['tarifs']) && is_array($_POST['tarifs'])) {
                    foreach ($_POST['tarifs'] as $tarifId) {
                        $tarif = $this->tarifsRepository->findById((int)$tarifId);
                        if ($tarif) {
                            $event->addTarif($tarif);
                        }
                    }
                }

                $this->repository->update($event);

                // Supprimer et recréer les dates d'inscription
                $this->inscriptionDatesRepository->deleteByEventId($id);
                if (!empty($_POST['inscription_dates']) && is_array($_POST['inscription_dates'])) {
                    foreach ($_POST['inscription_dates'] as $dateData) {
                        if (!empty($dateData['libelle']) && !empty($dateData['start_at']) && !empty($dateData['close_at'])) {
                            $inscriptionDate = new EventInscriptionDates();
                            $inscriptionDate->setEvent($id)
                                ->setLibelle($dateData['libelle'])
                                ->setStartRegistrationAt($dateData['start_at'])
                                ->setCloseRegistrationAt($dateData['close_at'])
                                ->setAccessCode($dateData['access_code'] ?? null);

                            $this->inscriptionDatesRepository->insert($inscriptionDate);
                        }
                    }
                }

                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Événement mis à jour'];
            }
            header('Location: /gestion/events');
            exit;
        }
    }

    #[Route('/gestion/events/delete/{id}', name: 'app_gestion_events_delete')]
    public function delete(int $id): void
    {
        $this->repository->delete($id);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Événement supprimé'];
        header('Location: /gestion/events');
        exit;
    }
}