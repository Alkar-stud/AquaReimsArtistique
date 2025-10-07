<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Tarif\TarifRepository;
use app\Services\DataValidation\ReservationDataValidationService;

class ReservationConfirmationController extends AbstractController
{
    private ReservationDataValidationService $reservationDataValidationService;
    private EventRepository $eventRepository;
    private TarifRepository $tarifRepository;
    private EventSessionRepository $eventSessionRepository;

    public function __construct(
        ReservationDataValidationService $reservationDataValidationService,
        EventRepository $eventRepository,
        TarifRepository $tarifRepository,
        EventSessionRepository $eventSessionRepository,
    )
    {
        parent::__construct(true); // route publique
        $this->reservationDataValidationService = $reservationDataValidationService;
        $this->eventRepository = $eventRepository;
        $this->tarifRepository = $tarifRepository;
        $this->eventSessionRepository = $eventSessionRepository;
    }

    /**
     * On confirme les données de toutes les étapes
     */
    #[Route('/reservation/confirmation', name: 'app_reservation_confirmation')]
    public function index(): void
    {
        // Met à jour le timestamp
        $_SESSION['reservation']['last_activity'] = time();
        $session = $this->reservationSessionService->getReservationSession();

        //On valide toutes les étapes
        if (!$this->reservationDataValidationService->checkPreviousStep(1, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur 1 dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }
        if (!$this->reservationDataValidationService->checkPreviousStep(2, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur 2 dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }
        if (!$this->reservationDataValidationService->checkPreviousStep(3, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur 3 dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }

        if (!$this->reservationDataValidationService->checkPreviousStep(6, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur 6 dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }

        //On récupère les infos de l'évent
        $event = $this->eventRepository->findById($session['event_id'], true);
        $tarifs = $this->tarifRepository->findByEventId($session['event_id']);
        $tarifsById = [];
        foreach ($tarifs as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }
        $eventSession = $this->eventSessionRepository->findById($session['event_session_id']);
        $swimmer = null;
        if (!$event->getLimitationPerSwimmer()) {

        }
/*
echo '<pre>';
print_r($session);
echo '<hr>';
print_r($event);
echo '<hr>';
print_r($tarifsById);
echo '<hr>';
print_r($eventSession);
die;
*/

        $this->render('reservation/confirmation', [
            'reservation'           => $session,
            'event'                 => $event,
            'tarifs'                => $tarifsById,
            'eventSession'          => $eventSession,
            'swimmer'               => $swimmer,
        ], 'Réservations');
    }



}