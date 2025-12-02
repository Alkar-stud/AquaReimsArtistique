<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Services\DataValidation\ReservationDataValidationService;
use app\Services\Payment\PaymentService;
use app\Services\Reservation\ReservationTempWriter;
use app\Services\Reservation\ReservationSaveCartService;

class ReservationConfirmationController extends AbstractController
{
    private ReservationDataValidationService $reservationDataValidationService;
    private EventSessionRepository $eventSessionRepository;
    private SwimmerRepository $swimmerRepository;
    private ReservationSaveCartService $reservationSaveCartService;
    private ReservationTempWriter $reservationTempWriter;
    private PaymentService $paymentService;
    private ReservationRepository $reservationRepository;

    public function __construct(
        ReservationDataValidationService $reservationDataValidationService,
        EventSessionRepository           $eventSessionRepository,
        SwimmerRepository                $swimmerRepository,
        ReservationSaveCartService       $reservationSaveCartService,
        ReservationTempWriter            $reservationTempWriter,
        PaymentService                   $paymentService,
        ReservationRepository            $reservationRepository,
    )
    {
        parent::__construct(true); // route publique
        $this->reservationDataValidationService = $reservationDataValidationService;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->swimmerRepository = $swimmerRepository;
        $this->reservationSaveCartService = $reservationSaveCartService;
        $this->reservationTempWriter = $reservationTempWriter;
        $this->paymentService =  $paymentService;
        $this->reservationRepository = $reservationRepository;
    }

    /**
     * On confirme les données de toutes les étapes
     */
    #[Route('/reservation/confirmation', name: 'app_reservation_confirmation')]
    public function index(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée
        $sessionTemp = $session['reservation'] ?? null;
        if (!$sessionTemp) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=rcc');
        }

        //On vérifie toutes les étapes.
        if (!$this->reservationDataValidationService->validateAllStep($session)) {
            $this->flashMessageService->setFlashMessage('warning', 'Il y a des erreurs dans le panier. Merci de recommencer votre réservation.');
            $this->redirect('/reservation');
        }

        //On récupère les infos de l'évent avec les tarifs associés
        $event = $session['reservation']->getEventObject();
        //On fait un tableau des tarifs indexés par leur ID
        $tarifsById = [];
        foreach ($event->getTarifs() as $tarif) {
            $tarifsById[$tarif->getId()] = $tarif;
        }
        //On récupère la session choisie de l'événement
        $eventSession = $this->eventSessionRepository->findById($session['reservation']->getEventSession());
        //On vérifie que la session appartient bien à l'événement courant
        if ($eventSession && $eventSession->getEventId() !== (int)$session['reservation']->getEvent()) {
            $eventSession = null;
        }

        //On récupère la nageuse
        $swimmer = null;
        if ($session['reservation']->getEventObject()->getLimitationPerSwimmer() !== null) {
            $swimmer = $this->swimmerRepository->findById($session['reservation']->getSwimmerId(), true);
        }

        // Préparation des détails et compléments pour la vue + calcul du grand total à partir des sous-totaux
        $detailSummary = $this->reservationSaveCartService->prepareReservationDetailSummary($session['reservation_details'], $tarifsById);
        $reservationDetails = $detailSummary['details'];
        $detailsSubtotal = $detailSummary['subtotal'];

        $complementSummary = $this->reservationSaveCartService->prepareReservationComplementSummary($session['reservation_complements'] ?? [], $tarifsById);
        $reservationComplements = $complementSummary['complements'];
        $complementsSubtotal = $complementSummary['subtotal'];

        $totalAmount = $detailsSubtotal + $complementsSubtotal;

        $this->render('reservation/confirmation', [
            'reservation'   => $session,
            'details'       => $reservationDetails,
            'complements'   => $reservationComplements,
            'grandTotal'    => $totalAmount,
            'event'         => $event,
            'tarifs'        => $tarifsById,
            'eventSession'  => $eventSession,
            'swimmer'       => $swimmer,
        ], 'Réservations');
    }


    #[Route('/reservation/payment', name: 'app_reservation_payment')]
    public function payment(): void
    {
        //On récupère la session
        $session = $this->reservationSessionService->getReservationTempSession();

        //On vérifie si la session est expirée
        $sessionTemp = $session['reservation'] ?? null;
        if (!$sessionTemp) {
            $this->flashMessageService->setFlashMessage('warning', 'Votre session a expiré. Merci de recommencer votre réservation.');
            $this->redirect('/reservation?session_expiree=rcc');
        }

        //On vérifie toutes les étapes.
        if (!$this->reservationDataValidationService->validateAllStep($session)) {
            $this->flashMessageService->setFlashMessage('warning', 'Il y a des erreurs dans le panier. Merci de recommencer votre réservation.');
            $this->redirect('/reservation');
        }

        //On prépare le panier pour la sauvegarde
        $reservation = $this->reservationSaveCartService->prepareReservationToSaveTemporarily($session);

        // Sauvegarde le panier
        $newId = $this->reservationTempWriter->saveReservation($reservation);
        //Pour retrouver après et envoyer si besoin à HelloAsso
        $this->reservationSessionService->setReservationSession('primary_id', $newId);
        //Et on met à jour $session
        $session = $this->reservationSessionService->getReservationSession();
        $reservation['primary_id'] = $newId;

        // On tente de créer l'intention de paiement
        $paymentResult = $this->paymentService->handlePayment($reservation, $session);

        // Si la création échoue...
        if ($paymentResult['success'] === false) {
            // On affiche le message d'erreur précis retourné par le service
            $this->flashMessageService->setFlashMessage('danger', 'Erreur de paiement : ' . ($paymentResult['error'] ?? 'Une erreur inconnue est survenue.'));
            // Et on redirige l'utilisateur vers la page de confirmation pour qu'il puisse corriger
            $this->redirect('/reservation/confirmation');
        } elseif ($paymentResult['success'] === true && isset($paymentResult['token'])) {
            // Si on a déjà un token, c'est que c'était un panier à 0€, on renvoie directement sur la bonne route
            $this->redirect('/reservation/merci?token=' . $paymentResult['token']);
        }

        // Si tout s'est bien passé, on affiche la page de paiement avec l'URL de redirection
        $this->render('reservation/payment', [
            'redirectUrl' => $paymentResult['redirectUrl'],
        ], 'Paiement de votre réservation');
    }


    #[Route('/reservation/success-payment', name: 'app_reservation_success-payment')]
    public function success(): void
    {
        $this->render('reservation/payment-success', [
        ], 'Réservation confirmée');
    }

    #[Route('/reservation/merci', name: 'app_reservation_merci')]
    public function merci(): void
    {
        $token = $_GET['token'] ?? null;
        if (!$token) {
            $this->flashMessageService->setFlashMessage('warning', "Ce token n'est associé à aucune commande. Veuillez vérifier le lien présent dans votre mail ou vous rapprocher des organisateurs.");
        } else {
            $token = htmlspecialchars($token);
            //On va chercher la réservation pour afficher la date et heure ainsi que l'adresse du rendez-vous, en rappelant l'ouverture des portes.
            $reservation = $this->reservationRepository->findByField('token', $token, true, true, false);
        }

        $this->render('reservation/merci', [
            'reservation' => $reservation ?? null,
        ], 'Réservation confirmée');

    }

}