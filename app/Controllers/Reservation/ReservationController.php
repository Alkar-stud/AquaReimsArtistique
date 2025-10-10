<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventTarifRepository;
use app\Services\DataValidation\ReservationDataValidationService;
use app\Services\Event\EventQueryService;
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

    public function __construct(
        EventQueryService $eventQueryService,
        ReservationSessionService $reservationSessionService,
        SwimmerQueryService $swimmerQueryService,
        ReservationDataValidationService $reservationDataValidationService,
        EventTarifRepository $eventTarifRepository,
        TarifService $tarifService,
        EventRepository $eventRepository,
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
    }

    /**
     * Page d'accueil du processus de réservation
     */
    #[Route('/reservation', name: 'app_reservation')]
    public function index(): void
    {
        // On commence une nouvelle session de réservation, on nettoie les anciennes données de $_SESSION.
        $this->reservationSessionService->clearReservationSession();
        // On récupère toutes les données nécessaires pour l'affichage des événements
        $events = $this->eventQueryService->getAllEventsWithRelations(true);

        // On détermine les statuts des périodes d'inscription pour ces événements
        $inscriptionPeriodsStatus = $this->eventQueryService->getEventInscriptionPeriodsStatus($events);

        //À récupérer seulement s'il y a un event qui en a besoin, sinon on envoie un tableau vide
        // On récupère les nageurs triés par groupe.
        $swimmerPerGroup = $this->swimmerQueryService->getSwimmerByGroup();

        // On récupère uniquement les groupes actifs qui ont des nageurs.
        $groupes = $this->swimmerQueryService->getActiveGroupsWithSwimmers(array_keys($swimmerPerGroup));

        $this->render('reservation/etape1', [
            'events' => $events,
            'periodesOuvertes' => $inscriptionPeriodsStatus['periodesOuvertes'],
            'nextPublicOuvertures' => $inscriptionPeriodsStatus['nextPublicOuvertures'],
            'periodesCloses' => $inscriptionPeriodsStatus['periodesCloses'],
            'groupes' => $groupes,
            'swimmerPerGroup' => $swimmerPerGroup
        ], 'Réservations');
    }


    #[Route('/reservation/etape2Display', name: 'etape2Display')]
    public function etape2Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationSession();

        //On vérifie si la session est expirée
        if (!$session || $this->reservationSessionService->isReservationSessionExpired($session)) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r2');
        }

        // Valider l'étape 1 avec le DTO
        $this->reservationDataValidationService->checkPreviousStep(1, $session);

        $this->render('reservation/etape2', [
            'reservation' => $session,
        ],'Réservations');

    }

    #[Route('/reservation/etape3Display', name: 'etape3Display', methods: ['GET'])]
    public function etape3Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationSession();

        //On vérifie si la session est expirée
        if (!$session || $this->reservationSessionService->isReservationSessionExpired($session)) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r3');
        }

        // Valider l'étape 1
        if (!$this->reservationDataValidationService->checkPreviousStep(1, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours 1, veuillez recommencer');
            $this->redirect('/reservation');
        }
        // Valider l'étape 2
        if (!$this->reservationDataValidationService->checkPreviousStep(2, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours 2, veuillez recommencer');
            $this->redirect('/reservation');
        }

        //Récupération des limites et l'état de la réservation par nageur
        $swimmerLimitReached = $this->swimmerQueryService->getStateOfLimitPerSwimmer();

        // Récupération des tarifs avec place assise de cet event
        $allTarifsWithSeatForThisEvent = $this->eventTarifRepository->findTarifsByEvent($session['event_id']);

        // Préparation des données à envoyer à la vue, construit le "pré-remplissage" s'il existe déjà un tarif avec code en session à l'aide du tableau des tarifs de cet event
        $dataForViewSpecialCode = $this->tarifService->getAllTarifAndPrepareViewWithSpecialCode(
            $allTarifsWithSeatForThisEvent,
            $session,
            'reservation_detail'
        );
        //Préparation des données déjà saisie à cette étape, regroupé par id et quantité
        $arrayTarifForForm = $this->reservationSessionService->arraySessionForFormStep3($session['reservation_detail'], $allTarifsWithSeatForThisEvent);

        //On envoie aussi le tableau des détails, pour préremplir si on est dans le cas d'un retour au niveau des étapes
        $this->render('reservation/etape3', [
            'allTarifsWithSeatForThisEvent' => $allTarifsWithSeatForThisEvent,  // tous les objets Tarif de Event index par leur ID
            'swimmerLimit'                  => $swimmerLimitReached,            // limitReached=> true|false, limit=> int|null = limit max, currentReservations=> int|null = nb actuel
            'event_id'                      => $session['event_id'],
            'specialTarifSession'           => $dataForViewSpecialCode,         //Tarif du code spécial saisi en tableau
            'arrayTarifForForm'             => $arrayTarifForForm,
        ], 'Réservations');
    }

    #[Route('/reservation/etape4Display', name: 'etape4Display', methods: ['GET'])]
    public function etape4Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationSession();

        //On vérifie si la session est expirée
        if (!$session || $this->reservationSessionService->isReservationSessionExpired($session)) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r4');
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

        //On récupère la liste des tarifs à envoyer à la vue sous forme de tableau indexé avec les ID
        $tarifs = $this->tarifService->getIndexedTarifFromEvent($session['reservation_detail']);

        $this->render('reservation/etape4', [
            'reservation' => $session,
            'tarifs' => $tarifs
        ], 'Réservations');
    }

    #[Route('/reservation/etape5Display', name: 'etape5Display', methods: ['GET'])]
    public function etape5Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationSession();

        $this->render('reservation/etape5', [
            'reservation' => $session,
        ], 'Réservations');
    }

    #[Route('/reservation/etape6Display', name: 'etape6Display', methods: ['GET'])]
    public function etape6Display(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationSession();

        //On vérifie si la session est expirée
        if (!$session || $this->reservationSessionService->isReservationSessionExpired($session)) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=r6');
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
        $allTarifsWithoutSeatForThisEvent = $this->eventTarifRepository->findTarifsByEvent($session['event_id'], false);

        // Préparation des données à envoyer à la vue, construit le "pré-remplissage" s'il existe déjà un tarif avec code en session à l'aide du tableau des tarifs de cet event
        $dataForViewSpecialCode = $this->tarifService->getAllTarifAndPrepareViewWithSpecialCode(
            $allTarifsWithoutSeatForThisEvent,
            $session,
            'reservation_complement'
        );


        //On ajoute si les sièges sont numérotées pour le bouton retour dans la vue
        $event = $this->eventRepository->findById($session['event_id'], true);

        //Préparation des données déjà saisie à cette étape, regroupé par id et quantité
        $arrayTarifForForm = $this->reservationSessionService->arraySessionForFormStep3($session['reservation_complement'], $allTarifsWithoutSeatForThisEvent);

        $this->render('reservation/etape6', [
            'reservation'                       => $session,
            'previousStep'                      => $event->getPiscine()->getNumberedSeats() ? 'etape5Display' : 'etape4Display',
            'allTarifsWithoutSeatForThisEvent'  => $allTarifsWithoutSeatForThisEvent,
            'specialTarifSession'               => $dataForViewSpecialCode,         //Tarif du code spécial saisi en tableau
            'arrayTarifForForm'                 => $arrayTarifForForm,
        ], 'Réservations');
    }

}