<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventTarifRepository;
use app\Services\DataValidation\ReservationDataValidationService;
use app\Services\Event\EventQueryService;
use app\Services\Reservation\ReservationSessionService;
use app\Services\Swimmer\SwimmerQueryService;

class ReservationController extends AbstractController
{
    private EventQueryService $eventQueryService;
    private SwimmerQueryService $swimmerQueryService;
    private ReservationDataValidationService $reservationDataValidationService;
    private EventTarifRepository $eventTarifRepository;

    public function __construct(
        EventQueryService $eventQueryService,
        ReservationSessionService $reservationSessionService,
        SwimmerQueryService $swimmerQueryService,
        ReservationDataValidationService $reservationDataValidationService,
        EventTarifRepository $eventTarifRepository,
    )
    {
        // On déclare la route comme publique pour éviter la redirection vers la page de login.
        parent::__construct(true);
        $this->eventQueryService = $eventQueryService;
        $this->reservationSessionService = $reservationSessionService;
        $this->swimmerQueryService = $swimmerQueryService;
        $this->reservationDataValidationService = $reservationDataValidationService;
        $this->eventTarifRepository = $eventTarifRepository;
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
            $this->redirect('/reservation?session_expiree=1');
        }

        // Valider l'étape 1 avec le DTO
        $result = $this->reservationDataValidationService->validatePreviousStep(1, $session);

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
            $this->redirect('/reservation?session_expiree=1');
        }

        // Valider l'étape 1
        if (!$this->reservationDataValidationService->validatePreviousStep(1, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }
        // Valider l'étape 2
        if (!$this->reservationDataValidationService->validatePreviousStep(2, $session)) {
            $this->flashMessageService->setFlashMessage('danger', 'Erreur dans le parcours, veuillez recommencer');
            $this->redirect('/reservation');
        }

        $eventId   = (int)($session['event_id'] ?? 0);
        $swimmerId = (int)($session['swimmer_id'] ?? 0);
        // Ne teste la limite que si un nageur est effectivement sélectionné
        $swimmerLimitReached = ['limitReached' => false, 'limit' => null];
        if ($swimmerId > 0) {
            $swimmerLimitReached = $this->swimmerQueryService->checkSwimmerLimit($eventId, $swimmerId);
        }

        isset($swimmerLimitReached['currentReservations']) ? $currentReservations = $swimmerLimitReached['currentReservations'] : $currentReservations = null;

        //Récupération des tarifs avec place assise
        $allTarifsWithSeatForThisEvent = $this->eventTarifRepository->findSeatedTarifsByEvent($session['event_id']);

        // Construit le "pré-remplissage" s'il existe déjà un tarif avec code en session
        $specialTarifSession = null;
        $details = $session['reservation_detail'] ?? [];
        if (is_array($details) && !empty($details)) {
            foreach ($details as $d) {
                $code = is_object($d) ? ($d->tarif_access_code ?? null) : ($d['tarif_access_code'] ?? null);
                $tarifId = (int)(is_object($d) ? ($d->tarif_id ?? 0) : ($d['tarif_id'] ?? 0));
                if (!$code || $tarifId <= 0) {
                    continue;
                }
                // Retrouve le tarif correspondant parmi les tarifs siégeants
                foreach ($allTarifsWithSeatForThisEvent as $tarif) {
                    if ($tarif->getId() === $tarifId) {
                        $specialTarifSession = [
                            'id'         => $tarif->getId(),
                            'name'       => $tarif->getName(),
                            'description'=> $tarif->getDescription(),
                            'seat_count' => $tarif->getSeatCount(),
                            'price'      => $tarif->getPrice(),
                            'code'       => $code,
                        ];
                        break 2; // on s'arrête au premier trouvé
                    }
                }
            }
        }
        //On envoie aussi le tableau des détails, pour préremplir si on est dans le cas d'un retour au niveau des étapes

        $this->render('reservation/etape3', [
            'allTarifsWithSeatForThisEvent' => $allTarifsWithSeatForThisEvent,
            'placesDejaReservees'           => $currentReservations,
            'limiteDepassee'                => $swimmerLimitReached['limitReached'],
            'limitation'                    => $swimmerLimitReached['limit'],
            'session'                       => $session,
            'specialTarifSession'           => $specialTarifSession,
        ], 'Réservations');
    }

}