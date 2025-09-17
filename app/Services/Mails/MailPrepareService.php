<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservations;
use app\Repository\Event\EventsRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\TarifsRepository;
use app\Services\MailService;
use DateMalformedStringException;

class MailPrepareService
{
    private MailService $mailService;

    public function __construct()
    {
        $this->mailService = new MailService();
    }


    /**
     * Envoie un email de réinitialisation de mot de passe.
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $username Le nom de l'utilisateur pour la personnalisation.
     * @param string $resetLink Le lien de réinitialisation à inclure.
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function sendPasswordResetEmail(string $recipientEmail, string $username, string $resetLink): bool
    {
        // On appelle la méthode générique avec les bons paramètres
        return $this->mailService->send($recipientEmail, 'password_reset', [
            'username' => $username,
            'link' => $resetLink
        ]);
    }

    /**
     * Envoie un email suite changement du mot de passe
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $username Le nom de l'utilisateur pour la personnalisation.
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function sendPasswordModifiedEmail(string $recipientEmail, string $username): bool
    {
        // On appelle la méthode générique avec les bons paramètres
        return $this->mailService->send($recipientEmail, 'password_modified', [
            'username' => $username,
            'email_club' => EMAIL_CLUB
        ]);
    }


    /**
     * Envoie un email de confirmation de réservation
     *
     * @param Reservations $reservation L'objet réservation complet (avec EventObject et SessionObject hydratés).
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function sendReservationConfirmationEmail(Reservations $reservation): bool
    {
        return $this->_sendReservationConfirmation($reservation, 'paiement_confirme');
    }

    /**
     * Envoie un email de confirmation pour un complément de réservation.
     *
     * @param Reservations $reservation L'objet réservation complet.
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function sendReservationConfirmationAddEmail(Reservations $reservation): bool
    {
        return $this->_sendReservationConfirmation($reservation, 'paiement_confirme_add');
    }

    /**
     * Méthode privée pour préparer et envoyer les emails de confirmation de réservation.
     *
     * @param Reservations $reservation
     * @param string $templateCode
     * @return bool
     * @throws DateMalformedStringException
     */
    private function _sendReservationConfirmation(Reservations $reservation, string $templateCode): bool
    {
        // Pour construire le récapitulatif, nous avons besoin des détails et des tarifs
        $detailsRepository = new ReservationsDetailsRepository();
        $complementsRepository = new ReservationsComplementsRepository();
        $tarifsRepository = new TarifsRepository();

        $details = $detailsRepository->findByReservation($reservation->getId());
        $complements = $complementsRepository->findByReservation($reservation->getId());
        $tarifs = $tarifsRepository->findByEventId($reservation->getEvent());
        $tarifsById = [];
        foreach ($tarifs as $t) {
            $tarifsById[$t->getId()] = $t;
        }

        // Construction du récapitulatif HTML
        $nbTotalPlace = 0; // Uniquement les places assises
        $recapHtml = '';

        if (!empty($details)) {
            $recapHtml .= '<h4 style="margin-top: 15px; margin-bottom: 5px;">Participants avec places assises</h4>';
            $recapHtml .= '<table cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
            foreach ($details as $detail) {
                $tarif = $tarifsById[$detail->getTarif()] ?? null;
                if ($tarif && $tarif->getNbPlace() !== null) {
                    $nbTotalPlace++;
                }
                $prix = $tarif ? $tarif->getPrice() : 0;
                $recapHtml .= '<tr>';
                $recapHtml .= '<td style="border-bottom: 1px solid #ddd;">';
                $recapHtml .= htmlspecialchars($detail->getPrenom() . ' ' . $detail->getNom());
                if ($tarif) {
                    $recapHtml .= ' (Tarif: <em>' . htmlspecialchars($tarif->getLibelle()) . '</em>)';
                }
                if ($detail->getPlaceNumber()) {
                    $recapHtml .= ' &mdash; Place: <em>' . htmlspecialchars($detail->getPlaceNumber()) . '</em>';
                }
                $recapHtml .= '</td>';
                $recapHtml .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . number_format($prix / 100, 2, ',', ' ') . ' €</strong></td>';
                $recapHtml .= '</tr>';
            }
            $recapHtml .= '</table>';
        }

        if (!empty($complements)) {
            $recapHtml .= '<h4 style="margin-top: 15px; margin-bottom: 5px;">Tarifs sans places assises</h4>';
            $recapHtml .= '<table cellpadding="5" cellspacing="0" style="width: 100%; border-collapse: collapse;">';
            foreach ($complements as $complement) {
                $tarif = $tarifsById[$complement->getTarif()] ?? null;
                $qty = $complement->getQty();
                if ($tarif) {
                    $subtotal = $tarif->getPrice() * $qty;
                    $recapHtml .= '<tr>';
                    $recapHtml .= '<td style="border-bottom: 1px solid #ddd;">' . htmlspecialchars($tarif->getLibelle()) . ' (x' . $qty . ')</td>';
                    $recapHtml .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . number_format($subtotal / 100, 2, ',', ' ') . ' €</strong></td>';
                    $recapHtml .= '</tr>';
                }
            }
            $recapHtml .= '</table>';
        }

        // S'assurer que l'objet Event est hydraté
        if (!$reservation->getEventObject()) {
            $eventsRepository = new EventsRepository();
            $reservation->setEventObject($eventsRepository->findById($reservation->getEvent()));
        }

        $event = $reservation->getEventObject();
        $session = $event ? current(array_filter($event->getSessions(), fn($s) => $s->getId() === $reservation->getEventSession())) : null;

        // Logique pour le total à payer
        $totalAmount = $reservation->getTotalAmount();
        $totalAmountPaid = $reservation->getTotalAmountPaid();

        if ($totalAmountPaid >= $totalAmount) {
            $totalAPayerLabel = 'Total payé :';
            $totalAPayerColor = 'green';
            $montantAAfficher = $totalAmountPaid;
        } elseif ($totalAmountPaid > 0) {
            $totalAPayerLabel = 'Reste à payer :';
            $totalAPayerColor = 'orange';
            $montantAAfficher = $totalAmount - $totalAmountPaid;
        } else {
            $totalAPayerLabel = 'À payer :';
            $totalAPayerColor = 'red';
            $montantAAfficher = $totalAmount;
        }
        $totalAPayerHtml = "<strong style=\"color: $totalAPayerColor;\">$totalAPayerLabel</strong>";

        // On appelle la méthode générique avec les bons paramètres pour envoyer le mail
        return $this->mailService->send($reservation->getEmail(), $templateCode, [
            'URLPATH' => 'https://' . $_SERVER['HTTP_HOST'],
            'prenom' => $reservation->getPrenom(),
            'token' => $reservation->getToken(),
            'IDreservation' => $reservation->getId(),
            'EventLibelle' => $event?->getLibelle() ?? 'N/A',
            'DateEvent' => $session?->getEventStartAt()->format('d/m/Y H:i') ?? 'N/A',
            'OpenDoorsAt' => $session?->getOpeningDoorsAt()->format('d/m/Y H:i') ?? 'N/A',
            'Piscine' => $event?->getPiscine() ? ($event->getPiscine()->getLibelle() . ' (' . $event->getPiscine()->getAdresse() . ')') : 'N/A',
            'ReservationNomPrenom' => $reservation->getPrenom() . ' ' . $reservation->getNom(),
            'Reservationmail' => $reservation->getEmail(),
            'Reservationtel' => $reservation->getPhone() ?? 'Non fourni',
            'ReservationNbTotalPlace' => $nbTotalPlace,
            'AffichRecapDetailPlaces' => $recapHtml,
            'TotalAPayer' => $totalAPayerHtml,
            'ReservationMontantTotal' => number_format($montantAAfficher / 100, 2, ',', ' ') . ' €',
            'SIGNATURE' => $_ENV['MAIL_SIGNATURE'] ?? 'L\'équipe Aqua Reims Artistique'
        ]);
    }

}