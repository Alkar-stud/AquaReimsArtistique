<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventTarifRepository;
use app\Services\DataValidation\ReservationDataValidationService;
use app\Services\Event\EventQueryService;
use app\Services\Reservation\ReservationQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;
use app\Services\Tarif\TarifService;

class ReservationController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private SwimmerQueryService $swimmerQueryService;
    private ReservationDataValidationService $reservationDataValidationService;
    private EventTarifRepository $eventTarifRepository;
    private TarifService $tarifService;
    private EventRepository $eventRepository;
    private ReservationQueryService $reservationQueryService;

    public function __construct(
        EventQueryService $eventQueryService,
        ReservationSessionService $reservationSessionService,
        SwimmerQueryService $swimmerQueryService,
        ReservationDataValidationService $reservationDataValidationService,
        EventTarifRepository $eventTarifRepository,
        TarifService $tarifService,
        EventRepository $eventRepository,
        ReservationQueryService $reservationQueryService,
    )
    {
        // On déclare la route comme publique pour éviter la redirection vers la page de login.
        parent::__construct(true);
        $this->eventQueryService = $eventQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->swimmerQueryService = $swimmerQueryService;
        $this->reservationDataValidationService = $reservationDataValidationService;
        $this->eventTarifRepository = $eventTarifRepository;
        $this->tarifService = $tarifService;
        $this->eventRepository = $eventRepository;
        $this->reservationQueryService = $reservationQueryService;
    }

    /**
     * Page d'accueil du processus de réservation
     */
    #[Route('/reservation', name: 'app_reservation')]
    public function etape1Display(): void
    {
        // Avant de récupérer la session en cours, on nettoie toutes les réservations temporaires expirées
        ReservationSessionService::clearExpiredSessions();
        // On commence une nouvelle session de réservation, on nettoie les anciennes données.
        $this->reservationSessionService->clearReservationSession();
        // On récupère toutes les données nécessaires pour l'affichage des événements
        $events = $this->eventQueryService->getAllEventsWithRelations(true);

        // On détermine les statuts des périodes d'inscription pour ces événements
        $inscriptionPeriodsStatus = $this->eventQueryService->getEventInscriptionPeriodsStatus($events);

        // On récupère les nageurs triés par groupe
        $swimmerPerGroup = $this->swimmerQueryService->getSwimmerByGroup();

        // On récupère uniquement les groupes actifs qui ont des nageurs.
        $groupes = $this->swimmerQueryService->getActiveGroupsWithSwimmers(array_keys($swimmerPerGroup));

        //On récupère le nombre de spectateurs par session
        $nbSpectatorsPerSession = $this->reservationQueryService->getNbSpectatorsPerSession($events);

        $this->render('reservation/etape1', [
            'events' => $events,
            'periodesOuvertes' => $inscriptionPeriodsStatus['periodesOuvertes'],
            'nextPublicOuvertures' => $inscriptionPeriodsStatus['nextPublicOuvertures'],
            'periodesCloses' => $inscriptionPeriodsStatus['periodesCloses'],
            'groupes' => $groupes,
            'swimmerPerGroup' => $swimmerPerGroup,
            'nbSpectatorsPerSession' => $nbSpectatorsPerSession,
        ], 'Réservations');
    }


    #[Route('/reservation/etape2Display', name: 'etape2Display')]
    public function etape2Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée
        if (!$session) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r2');
        }

        // Valider l'étape 1
        $result = $this->reservationDataValidationService->checkPreviousStep(1, $session);

        if (!$result['success']) {
            $this->flashMessageService->setFlashMessage('warning', 'Erreur de validation des données. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_erreur=cps1');
        }

        $this->render('reservation/etape2', [
            'reservation' => $session,
        ],'Réservations');

    }

    #[Route('/reservation/etape3Display', name: 'etape3Display', methods: ['GET'])]
    public function etape3Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée
        $reservationTemp = $session['reservation'] ?? null;
        if (!$reservationTemp) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r2');
        }

        // Valider l'étape 1
        $result = $this->reservationDataValidationService->checkPreviousStep(1, $session);
        if (!$result['success']) {
            $this->flashMessageService->setFlashMessage('warning', 'Erreur de validation des données de l\'étape 1. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_erreur=cps1');
        }
        // Valider l'étape 2
        $result = $this->reservationDataValidationService->checkPreviousStep(2, $session);
        if (!$result['success']) {
            $this->flashMessageService->setFlashMessage('warning', 'Erreur de validation des données de l\'étape 2. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_erreur=cps2');
        }

        //Récupération des limites et l'état de la réservation par nageur
        $swimmerLimitReached = $this->swimmerQueryService->getStateOfLimitPerSwimmer($session);

        // Récupération des tarifs avec place assise de cet event
        $allTarifsWithSeatForThisEvent = $this->eventTarifRepository->findTarifsByEvent($session['reservation']->getEvent());

        // Préparation des données à envoyer à la vue, construit le "pré-remplissage" s'il existe déjà un tarif avec code en session à l'aide du tableau des tarifs de cet event
        $dataForViewSpecialCode = $this->tarifService->getAllTarifAndPrepareViewWithSpecialCode(
            $allTarifsWithSeatForThisEvent,
            $session['reservation_details']
        );

        //Préparation des données déjà saisie à cette étape, regroupé par id et quantité
        $arrayTarifForForm = $this->reservationSessionService->getTarifQuantitiesFromDetails($session['reservation_details'], $allTarifsWithSeatForThisEvent);

        //On envoie aussi le tableau des détails, pour préremplir si on est dans le cas d'un retour au niveau des étapes
        $this->render('reservation/etape3', [
            'reservation'                   => $session,
            'allTarifsWithSeatForThisEvent' => $allTarifsWithSeatForThisEvent,  // tous les objets Tarif de Event index par leur ID
            'swimmerLimit'                  => $swimmerLimitReached,            // limitReached=> true|false, limit=> int|null = limit max, currentReservations=> int|null = nb actuel
            'specialTarifSession'           => $dataForViewSpecialCode,         //Tarif du code spécial saisi en tableau
            'arrayTarifForForm'             => $arrayTarifForForm,
        ], 'Réservations');
    }

    #[Route('/reservation/etape4Display', name: 'etape4Display', methods: ['GET'])]
    public function etape4Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée
        $reservationTemp = $session['reservation'] ?? null;
        if (!$reservationTemp) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r2');
        }

        // Valider l'étape 1
        if (!$this->reservationDataValidationService->checkPreviousStep(1, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }
        // Valider l'étape 2
        if (!$this->reservationDataValidationService->checkPreviousStep(2, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }

        // Valider l'étape 3
        if (!$this->reservationDataValidationService->checkPreviousStep(3, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }

        $this->render('reservation/etape4', [
            'reservation' => $session,
        ], 'Réservations');
    }

    #[Route('/reservation/etape5Display', name: 'etape5Display', methods: ['GET'])]
    public function etape5Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée
        $reservationTemp = $session['reservation'] ?? null;
        if (!$reservationTemp) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r2');
        }

        // Valider l'étape 1
        if (!$this->reservationDataValidationService->checkPreviousStep(1, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }
        // Valider l'étape 2
        if (!$this->reservationDataValidationService->checkPreviousStep(2, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }
        // Valider l'étape 3
        if (!$this->reservationDataValidationService->checkPreviousStep(3, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }
        // Valider l'étape 4
        if (!$this->reservationDataValidationService->checkPreviousStep(4, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }

        //On récupère la piscine de cet event
        $event = $this->eventRepository->findById($session['reservation']->getEvent(), true);
        if (!$event) {
            $this->flashMessageService->setFlashMessage('danger', 'Évènement inconnu, veuillez recommencer');
            $this->redirect('/reservation'); // ou page d'erreur
        }
        //On récupère la piscine de cet event
        $piscine = $event->getPiscine();

        if (!$piscine || !$piscine->getNumberedSeats()) {
            $this->redirect('/reservation/etape6Display');
        }

        //On récupère la liste des places leur statut sous forme de tableau pour l'envoyer à la vue
        //On récupère la piscine de l'event.
        $listPlacesAndStatus = $this->reservationQueryService->getAllSeatsInSwimmingPoolWithStatus($piscine);

        $this->render('reservation/etape5', [
            'reservation' => $session,
            'zones' => $listPlacesAndStatus['zones'],
        ], 'Réservations');
    }

    #[Route('/reservation/etape6Display', name: 'etape6Display', methods: ['GET'])]
    public function etape6Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée
        $reservationTemp = $session['reservation'] ?? null;
        if (!$reservationTemp) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r2');
        }

        //Il faut valider les étapes précédentes
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

        // Récupération des tarifs avec tarif sans place non assise de cet event
        $allTarifsWithoutSeatForThisEvent = $this->eventTarifRepository->findTarifsByEvent($session['reservation']->getEvent(), false);

        // Préparation des données à envoyer à la vue, construit le "pré-remplissage" s'il existe déjà un tarif avec code en session à l'aide du tableau des tarifs de cet event
        $dataForViewSpecialCode = $this->tarifService->getAllTarifAndPrepareViewWithSpecialCode(
            $allTarifsWithoutSeatForThisEvent,
            $session['reservation_complements']
        );

        //On ajoute si les sièges sont numérotées pour le bouton retour dans la vue
        $event = $this->eventRepository->findById($session['reservation']->getEvent(), true);

        //Préparation des données déjà saisie à cette étape, regroupé par id et quantité
        $arrayTarifForForm = $this->reservationSessionService->getComplementQuantities($session['reservation_complements']);

        $this->render('reservation/etape6', [
            'reservation'                       => $session,
            'previousStep'                      => $event->getPiscine()->getNumberedSeats() ? 'etape5Display' : 'etape4Display',
            'allTarifsWithoutSeatForThisEvent'  => $allTarifsWithoutSeatForThisEvent,
            'specialTarifSession'               => $dataForViewSpecialCode,         //Tarif du code spécial saisi en tableau
            'arrayTarifForForm'                 => $arrayTarifForForm,
        ], 'Réservations');
    }

}