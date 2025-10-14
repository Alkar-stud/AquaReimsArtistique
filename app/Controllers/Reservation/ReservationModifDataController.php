<?php

namespace app\Controllers\Reservation;


use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Reservation\ReservationQueryService;
use app\Services\Reservation\ReservationUpdateService;
use DateTime;
use Exception;
use InvalidArgumentException;

class ReservationModifDataController extends AbstractController
{
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationQueryService $reservationQueryService,
    )
    {
        parent::__construct(true); // route publique
        $this->reservationRepository = $reservationRepository;
        $this->reservationQueryService = $reservationQueryService;
    }

    /**
     * Pour afficher le contenu de la réservation
     *
     * @throws Exception
     */
    #[Route('/modifData', name: 'app_reservation_modif_data')]
    public function modifData(): void
    {
        // On récupère et valide le token depuis l'URL
        $token = $_GET['token'] ?? null;
        if (!$token || !ctype_alnum($token)) {
            http_response_code(404);
            throw new Exception('404');
        }

        // On récupère la réservation par son token
        $reservation = $this->reservationRepository->findByField('token', $token, true, true, true);
        if (!$reservation) {
            http_response_code(404);
            throw new Exception('404');
        }

        //On vérifie si c'est encore modifiable (annulation ou date de fin d'inscription dépassée)
        $canBeModified = $this->reservationQueryService->checkIfReservationCanBeModified($reservation);

        if (!$canBeModified) {
            $this->flashMessageService->setFlashMessage('danger', 'La modification n\'est plus possible.');
        }

        //On prépare les détails et les compléments pour la vue
        $readyForView = $this->reservationQueryService->prepareReservationDetailsAndComplementsToView($reservation);

        // Calcul du montant dû (en centimes)
        $amountDue = $reservation->getTotalAmount() - $reservation->getTotalAmountPaid();

        // Calcul du montant total de la commande (en centimes) pour le calcul du don
        $grandTotal = $readyForView['totals']['total_amount'] ?? $reservation->getTotalAmount();

        // Calcul du don maximum (en euros, formaté pour l'attribut HTML)
        // (total / 100 pour passer en euros) * (pourcentage / 100)
        $maxDonationEuros = number_format(($grandTotal / 100) * (DONATION_SLIDER_MAX_PERCENTAGE / 100), 2, '.', '');


        $this->render('reservation/modif_data', [
            'reservation' => $reservation,
            'reservationView' => $readyForView,
            'canBeModified' => $canBeModified,
            'amountDue' => $amountDue,
            'maxDonationEuros' => $maxDonationEuros,
        ], 'Récapitulatif de la réservation');

    }

    #[Route('/modifData/update', name: 'app_reservation_update', methods: ['POST'])]
    public function update(): void
    {
        $return = ['success' => false, 'message' => 'pas d\'erreur !'];

        //On récupère toutes les données susceptibles d'être envoyées
        $data = json_decode(file_get_contents('php://input'), true);
        $typeField = $data['typeField'];
        $token = $data['token'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;

        //Si pas de token
        if (!$token || !ctype_alnum($token)) {
            $this->json(['success' => false, 'message' => 'Modification non autorisée.']);
            return;
        }
        //On récupère la réservation
        $reservation = $this->reservationRepository->findByField('token', $token, false, false, false, false);
        if (!$reservation) {
            $this->json(['success' => false, 'message' => 'Modification non autorisée.']);
        }

        // On vérifie que le token est toujours valide
        if ($reservation->getTokenExpireAt() < new DateTime) {
            $this->json(['success' => false, 'message' => 'La modification n\'est plus autorisée.']);
        }

        if ($typeField == 'contact') {
            try {
                $updateService = new ReservationUpdateService($this->reservationRepository);
                $success = $updateService->updateContactField($reservation, $field, $value);
                $return = [
                    'success' => $success,
                    'message' => $success ? 'Mise à jour réussie.' : 'La mise à jour a échoué.'
                ];
            } catch (InvalidArgumentException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
        }


        $this->json($return);
    }


}