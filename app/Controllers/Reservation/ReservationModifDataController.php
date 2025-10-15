<?php

namespace app\Controllers\Reservation;


use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Reservation\ReservationRepository;
use app\Repository\Tarif\TarifRepository;
use app\Services\Reservation\ReservationQueryService;
use app\Services\Reservation\ReservationUpdateService;
use DateTime;
use Exception;
use InvalidArgumentException;

class ReservationModifDataController extends AbstractController
{
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;
    private ReservationUpdateService $reservationUpdateService;
    private TarifRepository $tarifRepository;

    public function __construct(
        ReservationRepository $reservationRepository,
        ReservationQueryService $reservationQueryService,
        ReservationUpdateService $reservationUpdateService,
        TarifRepository $tarifRepository,
    )
    {
        parent::__construct(true); // route publique
        $this->reservationRepository = $reservationRepository;
        $this->reservationQueryService = $reservationQueryService;
        $this->reservationUpdateService = $reservationUpdateService;
        $this->tarifRepository = $tarifRepository;
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

        // Récupérer les compléments disponibles pour l'ajout
        $existingComplementTarifIds = array_keys($readyForView['complements'] ?? []);
        $availableComplements = $this->tarifRepository->findAvailableComplementsForEvent(
            $reservation->getEvent(),
            $existingComplementTarifIds
        );

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
            'availableComplements' => $availableComplements,
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
        $fieldId = $data['id'] ?? null;
        $tarifId = $data['tarifId'] ?? null;
        $token = $data['token'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;
        $action = $data['action'] ?? null;

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
                $success = $this->reservationUpdateService->updateContactField($reservation, $field, $value);
                $return = [
                    'success' => $success,
                    'message' => $success ? 'Mise à jour réussie.' : 'La mise à jour a échoué.'
                ];
            } catch (InvalidArgumentException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
        } elseif ($typeField == 'detail') {
            try {
                $success = $this->reservationUpdateService->updateDetailField((int)$fieldId, $field, $value);
                $return = [
                    'success' => $success,
                    'message' => $success ? 'Mise à jour réussie.' : 'La mise à jour a échoué.'
                ];
            } catch (InvalidArgumentException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
        } elseif ($typeField == 'complement') {
            try {
                if ($fieldId) { // Mise à jour d'un complément existant
                    $success = $this->reservationUpdateService->updateComplementQuantity($reservation->getId(), (int)$fieldId, $action);
                    $return = [
                        'success' => $success,
                        'message' => $success ? 'Mise à jour réussie.' : 'La mise à jour a échoué.',
                        'reload' => $success // Demander un rechargement si succès
                    ];
                } elseif ($tarifId) { // Ajout d'un nouveau complément
                    $success = $this->reservationUpdateService->addComplement($reservation->getId(), (int)$tarifId);
                    $return = [
                        'success' => $success,
                        'message' => $success ? 'Complément ajouté avec succès.' : "Erreur lors de l'ajout du complément.",
                        'reload' => $success
                    ];
                } else {
                    $return = ['success' => false, 'message' => 'Action sur complément non valide.'];
                }
            } catch (InvalidArgumentException $e) {
                $return = ['success' => false, 'message' => $e->getMessage()];
            }
        }


        $this->json($return);
    }


}