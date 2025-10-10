<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Repository\Tarif\TarifRepository;
use app\Services\DataValidation\ReservationDataValidationService;
use app\Services\Payment\PaymentService;
use app\Services\Reservation\ReservationTempWriter;
use app\Services\Reservation\ReservationSaveCartService;

class ReservationConfirmationController extends AbstractController
{
    private ReservationDataValidationService $reservationDataValidationService;
    private EventRepository $eventRepository;
    private EventSessionRepository $eventSessionRepository;
    private SwimmerRepository $swimmerRepository;
    private ReservationSaveCartService $reservationSaveCartService;
    private ReservationTempWriter $reservationTempWriter;
    private TarifRepository $tarifRepository;
    private PaymentService $paymentService;

    public function __construct(
        ReservationDataValidationService $reservationDataValidationService,
        EventRepository                  $eventRepository,
        TarifRepository                  $tarifRepository,
        EventSessionRepository           $eventSessionRepository,
        SwimmerRepository                $swimmerRepository,
        ReservationSaveCartService       $reservationSaveCartService,
        ReservationTempWriter            $reservationTempWriter,
        PaymentService                   $paymentService,
    )
    {
        parent::__construct(true); // route publique
        $this->reservationDataValidationService = $reservationDataValidationService;
        $this->eventRepository = $eventRepository;
        $this->eventSessionRepository = $eventSessionRepository;
        $this->swimmerRepository = $swimmerRepository;
        $this->reservationSaveCartService = $reservationSaveCartService;
        $this->tarifRepository = $tarifRepository;
        // On instancie le service de paiement avec ses dépendances
        $this->reservationTempWriter = $reservationTempWriter;
        $this->paymentService =  $paymentService;
    }

    /**
     * On confirme les données de toutes les étapes
     */
    #[Route('/reservation/confirmation', name: 'app_reservation_confirmation')]
    public function index(): void
    {
        $session = $this->reservationSessionService->getReservationSession();

        if (!isset($session['event_id'])) {
            $this->flashMessageService->setFlashMessage('danger', 'Le panier a expiré, veuillez recommencer');
            $this->redirect('/reservation?session_expiree=rcc');
        }

        //On vérifie toutes les étapes.
        if (!$this->reservationDataValidationService->validateAllPreviousStep($session)) {
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
        if ($event->getLimitationPerSwimmer() !== null) {
            $swimmer = $this->swimmerRepository->findById($session['swimmer_id'], true);
        }

        // Préparation des détails et compléments pour la vue + calcul du grand total à partir des sous-totaux
        $detailSummary = $this->reservationSaveCartService->prepareReservationDetailSummary($session['reservation_detail'], $tarifsById);
        $reservationDetails = $detailSummary['details'];
        $detailsSubtotal = $detailSummary['subtotal'];

        $complementSummary = $this->reservationSaveCartService->prepareReservationComplementSummary($session['reservation_complement'] ?? [], $tarifsById);
        $reservationComplements = $complementSummary['complements'];
        $complementsSubtotal = $complementSummary['subtotal'];

        $grandTotal = $detailsSubtotal + $complementsSubtotal;

        $this->render('reservation/confirmation', [
            'reservation'   => $session,
            'details'       => $reservationDetails,
            'complements'   => $reservationComplements,
            'grandTotal'    => $grandTotal,
            'event'         => $event,
            'tarifs'        => $tarifsById,
            'eventSession'  => $eventSession,
            'swimmer'       => $swimmer,
        ], 'Réservations');
    }


    #[Route('/reservation/payment', name: 'app_reservation_payment')]
    public function payment(): void
    {
        $session = $this->reservationSessionService->getReservationSession();

        if (!isset($session['event_id'])) {
            $this->flashMessageService->setFlashMessage('danger', 'Le panier a expiré, veuillez recommencer');
            $this->redirect('/reservation?session_expiree=rcp');
        }

        //On vérifie toutes les étapes.
        if (!$this->reservationDataValidationService->validateAllPreviousStep($session)) {
            $this->redirect('/reservation');
        }

        //On sauvegarde le panier en NoSQL
        $reservation = $this->reservationSaveCartService->prepareReservationToSaveInNoSQL($session);
        // Sauvegarde le panier
        $newId = $this->reservationTempWriter->saveReservation($reservation);
        //On récupère le tout sauvegardé dans la base de données
        $savedReservation = $this->reservationTempWriter->findReservationById($newId);

        // On tente de créer l'intention de paiement
        $paymentResult = $this->paymentService->handlePayment($savedReservation, $session);

        // Si la création échoue...
        if ($paymentResult['success'] === false) {
            // On affiche le message d'erreur précis retourné par le service
            $this->flashMessageService->setFlashMessage('danger', 'Erreur de paiement : ' . ($paymentResult['error'] ?? 'Une erreur inconnue est survenue.'));
            // Et on redirige l'utilisateur vers la page de confirmation pour qu'il puisse corriger
            $this->redirect('/reservation/confirmation');
        }

        // Si tout s'est bien passé, on affiche la page de paiement avec l'URL de redirection
        $this->render('reservation/payment', [
            'redirectUrl' => $paymentResult['redirectUrl'],
        ], 'Paiement de votre réservation');
    }


    #[Route('/reservation/success-payment', name: 'app_reservation_success-payment')]
    public function success(): void
    {
        //On récupère la réservation pour avoir le montant. La vue n'a pas la même chose à afficher selon si c'est 0€ ou plus
        $reservation = $this->reservationSessionService->getReservationSession();

        if (!isset($reservation['event_id'])) {
            $this->flashMessageService->setFlashMessage('danger', 'Le panier a expiré, veuillez recommencer');
            $this->redirect('/reservation?session_expiree=rcs');
        }

        $event = $this->eventRepository->findById((int)$reservation['event_id'], true);
        //On récupère dans $_GET checkoutIntentId et orderId et on ajoute orderId dans la BDD NoSQL

echo '<pre>reservation : ';
print_r($reservation);
die;

        //Si $_GET['code'] == 'succeeded', on demande au JS de la vue de vérifier si le callback a bien enregistré en définitif
        //C'est le JS qui va faire ça avec checkoutIntentId ou le primary_id.
        //C'est aussi le callback qui envoie le mail et qui fait nettoie les BDD NoSQL et la session
        //Si au bout d'un certain temps le callback n'a rien donné, on va chercher directement chez HelloAsso avec le checkoutIntentId
        //Si tout bon, le JS renvoi vers /reservation/merci avec le token généré à l'enregistrement de la réservation.


        $this->render('reservation/payment-success', [
            'reservation' => $reservation,
            'event'       => $event,

        ], 'Réservation confirmée');
    }


}