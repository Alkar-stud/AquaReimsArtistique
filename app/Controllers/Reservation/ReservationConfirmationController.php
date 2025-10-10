<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Reservation\ReservationPayment;
use app\Repository\Event\EventRepository;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Reservation\ReservationPaymentRepository;
use app\Repository\Reservation\ReservationRepository;
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
        } elseif ($paymentResult['success'] === true && isset($paymentResult['token'])) {
            // Si on a déjà un token, c'est que c'était un panier à 0€, on renvoie directement sur la bonne route
            $this->redirect('/reservation/merci');
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




    #[Route('/reservation/checkPayment', name: 'app_reservation_checkPayment', methods: ['POST'])]
    public function checkPayment(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $checkoutIntentId = $input['checkoutIntentId'] ?? null;
        if (!$checkoutIntentId) {
            $this->json(['success' => false, 'error' => 'checkoutId manquant']);
            return;
        }

        // Vérifier dans la BDD si un paiement avec cet ID a été enregistré (par le webhook)
        $paymentsRepository = new ReservationPaymentRepository();
        $payment = $paymentsRepository->findByCheckoutId((int)$checkoutIntentId);

        // Renvoyer le statut au front
        if ($payment && in_array($payment->getStatusPayment(), ['Authorized', 'Processed'])) {
            $this->handleSuccessfulCheck($payment);
        } else {
            // Le paiement n'est pas encore trouvé ou n'a pas le bon statut, on indique au front de patienter.
            $this->json(['success' => false, 'status' => 'pending']);
        }
    }

    #[Route('/reservation/merci', name: 'app_reservation_merci')]
    public function merci(): void
    {



        $this->render('reservation/merci', [

        ], 'Réservation confirmée');

    }


    /**
     * Gère la réponse JSON pour une vérification de paiement réussie.
     * @param ReservationPayment $payment
     * @return void
     */
    private function handleSuccessfulCheck(ReservationPayment $payment): void
    {
        $reservationsRepository = new ReservationRepository();
        $reservation = $reservationsRepository->findById($payment->getReservation());

        if ($reservation) {
            unset($_SESSION['reservation'][session_id()]);
            $this->json(['success' => true, 'token' => $reservation->getToken()]);
        } else {
            // Cas peu probable où le paiement existe, mais pas la réservation associée
            $this->json(['success' => false, 'error' => 'Paiement trouvé mais réservation introuvable.']);
        }
    }

    //C'est aussi le callback qui envoie le mail et qui fait nettoie les BDD NoSQL et la session
    //Si au bout d'un certain temps le callback n'a rien donné, on va chercher directement chez HelloAsso avec le checkoutIntentId
    //Si tout bon, le JS renvoi vers /reservation/merci avec le token généré à l'enregistrement de la réservation.



}