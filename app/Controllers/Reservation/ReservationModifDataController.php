<?php

namespace app\Controllers\Reservation;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Reservation\Reservations;
use app\Models\Reservation\ReservationsComplements;
use app\Models\Reservation\ReservationsDetails;
use app\Repository\Event\EventSessionRepository;
use app\Repository\Event\EventsRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\Reservation\ReservationsRepository;
use app\Services\Reservation\ReservationCartService;
use app\Repository\TarifsRepository;
use app\Services\Reservation\ReservationService;
use app\Services\Reservation\ReservationTokenService;
use DateMalformedStringException;

class ReservationModifDataController extends AbstractController
{
    private ReservationsRepository $reservationsRepository;
    private EventsRepository $eventsRepository;
    private EventSessionRepository $eventSessionRepository;
    private ReservationsDetailsRepository $reservationsDetailsRepository;
    private ReservationsComplementsRepository $reservationsComplementsRepository;
    private TarifsRepository $tarifsRepository;
    private ReservationCartService $reservationCartService;
    private ReservationTokenService $reservationTokenService;
    private ReservationService $reservationService;


    public function __construct()
    {
        parent::__construct(true); // route publique
        $this->reservationsRepository = new ReservationsRepository();
        $this->eventsRepository = new EventsRepository();
        $this->eventSessionRepository = new EventSessionRepository();
        $this->reservationsDetailsRepository = new ReservationsDetailsRepository();
        $this->reservationsComplementsRepository = new ReservationsComplementsRepository();
        $this->tarifsRepository = new TarifsRepository();
        $this->reservationCartService = new ReservationCartService();
        $this->reservationTokenService = new ReservationTokenService();
        $this->reservationService = new ReservationService();
    }

    /**
     * Pour afficher le contenu de la réservation
     *
     * @throws DateMalformedStringException
     */
    #[Route('/modifData', name: 'app_reservation_modif_data')]
    public function modifData(): void
    {
        // On récupère et valide le token depuis l'URL
        $token = $_GET['token'] ?? null;
        if (!$token || !ctype_alnum($token)) {
            http_response_code(404);
            // Idéalement, afficher une page 404 générique
            echo "Page non trouvée.";
            exit;
        }

        // On récupère la réservation par son token
        $reservation = $this->reservationsRepository->findByToken($token);
        if (!$reservation) {
            http_response_code(404);
            echo "Réservation non trouvée.";
            exit;
        }

        //On récupère l'événement et la session associés à la réservation
        $event = $this->eventsRepository->findById($reservation->getEvent());
        $session = $this->eventSessionRepository->findById($reservation->getEventSession());

        // On récupère les détails et compléments de la réservation
        $reservationDetails = $this->reservationsDetailsRepository->findByReservation($reservation->getId());
        $reservationComplements = $this->reservationsComplementsRepository->findByReservation($reservation->getId());

        // On récupère tous les tarifs pour pouvoir afficher les libellés
        $tarifs = $this->tarifsRepository->findByEventId($event->getId());
        $tarifsByIdObj = [];
        foreach ($tarifs as $t) {
            $tarifsByIdObj[$t->getId()] = $t;
        }

        // Le service attend un tableau de données, pas un tableau d'objets.
        // On convertit les objets ReservationDetails en tableaux.
        $detailsAsArray = array_map(function ($detail) {
            return ['tarif_id' => $detail->getTarif()];
        }, $reservationDetails);
        // Calculer les quantités correctes de tarifs (packs)
        $tarifQuantities = $this->reservationCartService->getTarifQuantitiesFromDetails($detailsAsArray, $tarifs);

        // Préparer la liste des compléments disponibles à l'achat
        $userComplementTarifIds = array_map(fn($c) => $c->getTarif(), $reservationComplements);
        $allComplementTarifs = $this->tarifsRepository->findByEventId($event->getId());

        // Filtrer les compléments disponibles
        $availableComplements = array_filter($allComplementTarifs, function($tarif) use ($userComplementTarifIds) {
            return !in_array($tarif->getId(), $userComplementTarifIds);
        });

        // Garder uniquement ceux dont getNbPlace() n'est pas NULL
        $availableComplements = array_filter($availableComplements, function($tarif) {
            return $tarif->getNbPlace() === null;
        });

        $this->render('reservation/modif_data', [
            'reservation' => $reservation,
            'session' => $session,
            'event' => $event,
            'reservationDetails' => $reservationDetails,
            'reservationComplements' => $reservationComplements,
            'availableComplements' => $availableComplements,
            'tarifQuantities' => $tarifQuantities,
            'tarifsByIdObj' => $tarifsByIdObj,
            'reservationUuid' => $reservation->getUuid(),
        ], 'Récapitulatif de la réservation');
    }

    /**
     * Pour récupérer les infos pour mettre à jour un champ
     *
     * @throws DateMalformedStringException
     */
    #[Route('/modifData/update', name: 'app_reservation_update', methods: ['POST'])]
    public function update(): void
    {
        //On récupère toutes les données susceptibles d'être envoyées
        $data = json_decode(file_get_contents('php://input'), true);
        $typeField = $data['typeField'];
        $token = $data['token'] ?? null;
        $fieldId = $data['id'] ?? null;
        $tarifId = $data['tarifId'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;
        $action = $data['action'] ?? null;

        //Si pas de token
        if (!$token || !ctype_alnum($token)) {
            $this->json(['success' => false, 'message' => 'Modification non autorisée.']);
            return;
        }
        //On récupère la réservation
        $reservation = $this->reservationsRepository->findByToken($token);

        // On vérifie que le token est existant dans la table et toujours valide
        if (!$this->reservationTokenService->checkReservationToken($reservation, $token)) {
            $this->json(['success' => false, 'message' => 'La modification n\'est plus autorisée.']);
        }

        // On applique les règles de casse pour champs pour lesquels c'est nécessaire
        $value = $this->reservationService->normalizeFieldValue($field, $value);


        if ($typeField == 'contact') {
            //Pour les infos de contact de la réservation
            $return = $this->updateContact($reservation, $field, $value);

        } elseif ($typeField == 'detail') {
            //Pour les infos des participants
            $detail = $this->reservationsDetailsRepository->findById((int)$fieldId);
            if (!$detail) {
                $this->json(['success' => false, 'message' => 'Participant non trouvé.']);
                return;
            }
            $return = $this->updateDetail($reservation, $detail, $field, $value);

        } elseif ($typeField == 'complement') {
            $return = [];
            //Pour les infos des compléments
            if ($fieldId) { // Mise à jour d'un complément existant
                // $value contient le code d'accès au tarif si besoin. Sinon la valeur est nulle
                $return = $this->updateComplements($reservation, $value, $fieldId, $action);

            } elseif ($tarifId) { // Ajout d'un nouveau complément
                // On vérifie si un complément avec ce tarif n'existe pas déjà
                $existingComplement = $this->reservationsComplementsRepository->findByReservationAndTarif($reservation->getId(), (int)$tarifId);
                if ($existingComplement) {
                    // Si oui, on incrémente sa quantité
                    $return = $this->updateComplements($reservation, $value, $fieldId, 'plus'); // On met plus car l'intention est d'ajouter
                } else {
                    // Sinon, on le crée
                    $newComplement = new ReservationsComplements();
                    $newComplement->setReservation($reservation->getId())->setTarif((int)$tarifId)->setQty(1)->setCreatedAt(date('Y-m-d H:i:s'));
                    $this->reservationsComplementsRepository->insert($newComplement);
                    $return = ['success' => true, 'message' => 'Article ajouté.'];
                }
            }

            if ($return['success'] === true) {
                // Recalculer le total de la réservation
                $allReservationDetails = $this->reservationsDetailsRepository->findByReservation($reservation->getId());
                $allReservationComplements = $this->reservationsComplementsRepository->findByReservation($reservation->getId());

                $detailsAsArray = array_map(fn($d) => ['tarif_id' => $d->getTarif()], $allReservationDetails);
                $complementsAsArray = array_map(fn($c) => ['tarif_id' => $c->getTarif(), 'qty' => $c->getQty()], $allReservationComplements);
                $newTotalAmount = $this->reservationCartService->calculateTotalAmount(['reservation_detail' => $detailsAsArray, 'reservation_complement' => $complementsAsArray, 'event_id' => $reservation->getEvent()]);

                // Mettre à jour le montant total dans la base de données
                $this->reservationsRepository->updateSingleField($reservation->getId(), 'total_amount', (string)$newTotalAmount);

                // Ajouter le nouveau total à la réponse JSON pour le client
                $return['newTotalAmount'] = $newTotalAmount;

            }
        } elseif ($typeField == 'cancel') {
            $return = $this->cancel($reservation);
        } else {
            $return = ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
        }

        $this->json($return);
    }

    public function updateContact(Reservations $reservation, $field, $value): array
    {
        $success = $this->reservationsRepository->updateSingleField($reservation->getId(), $field, $value);

        if ($success) {
            return ['success' => true, 'message' => 'Informations mises à jour.'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
        }
    }

    public function updateDetail(Reservations $reservation, ReservationsDetails $detail, string $field, string $value): array
    {
        $success = $this->reservationsDetailsRepository->updateSingleField($detail->getId(), $field, $value);

        if ($success) {
            return ['success' => true, 'message' => 'Participant mis à jour.'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    public function updateComplements(Reservations $reservation, ?string $tarif_access_code, int $fieldId, ?string $action): array
    {
        $complement = $this->reservationsComplementsRepository->findById($fieldId);

        $qty = $complement->getQty();
        if ($action == 'plus') { $qty ++; }
        else { $qty--; }
        //Si $qty <= 0 on supprime la ligne
        if ($qty <= 0) {
            $success = $this->reservationsComplementsRepository->delete($complement->getId());
            $message = 'Complément supprimé.';
        } else {
            $success = $this->reservationsComplementsRepository->updateQuantity($complement->getId(), $tarif_access_code, $qty);
            $message = 'Quantité mise à jour.';
        }

        if ($success) {
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de la mise à jour.'];
        }

    }

    public function cancel($reservation): array
    {
        $return = $this->reservationsRepository->cancelByToken($reservation->getToken());
        if ($return) {
            $return = $this->reservationsDetailsRepository->cancelByReservation($reservation->getId());
            if ($return) {
                return ['success' => true, 'message' => 'Réservation annulée.'];
            } else {
                return ['success' => false, 'message' => 'Erreur lors de l\'annulation.'];
            }
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'annulation.'];
        }
    }
}