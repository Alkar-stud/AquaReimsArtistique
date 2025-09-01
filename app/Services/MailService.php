<?php

namespace app\Services;

use app\Models\Reservation\Reservations;
use app\Repository\MailTemplateRepository;
use app\Repository\Reservation\ReservationsComplementsRepository;
use app\Repository\Reservation\ReservationsDetailsRepository;
use app\Repository\TarifsRepository;
use DateMalformedStringException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

class MailService
{
    private PHPMailer $mailer;
    private MailTemplateRepository $templateRepository;

    /**
     * Configure PHPMailer en utilisant un tableau de configuration validé.
     * @throws Exception Si une clé de configuration requise est manquante.
     */
    public function __construct()
    {
        // Valider que toutes les variables d'environnement nécessaires existent.
        $requiredKeys = ['MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_ENCRYPTION', 'MAIL_PORT', 'MAIL_FROM_ADDRESS'];
        foreach ($requiredKeys as $key) {
            if (empty($_ENV[$key])) {
                // On lance une exception claire si une variable manque.
                throw new Exception("Configuration MailService manquante : la variable d'environnement {$key} n'est pas définie.");
            }
        }

        $this->mailer = new PHPMailer(true);
        $this->templateRepository = new MailTemplateRepository();

        // Utiliser les variables maintenant qu'on sait qu'elles existent.
        $this->mailer->isSMTP();
        $this->mailer->Host = $_ENV['MAIL_HOST'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $_ENV['MAIL_USERNAME'];
        $this->mailer->Password = $_ENV['MAIL_PASSWORD'];
        $this->mailer->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $this->mailer->Port = (int)$_ENV['MAIL_PORT'];
        $this->mailer->CharSet = 'UTF-8';

        if (isset($_ENV['MAIL_MAILER']) && $_ENV['MAIL_MAILER'] === 'log') {
            $this->mailer->SMTPDebug = SMTP::DEBUG_SERVER;
        }

        $fromName = $_ENV['MAIL_FROM_NAME'] ?? 'Aqua Reims Artistique';
        $this->mailer->setFrom($_ENV['MAIL_FROM_ADDRESS'], $fromName);
    }

    /**
     * Prépare un email de réinitialisation de mot de passe.
     *
     * @param string $recipientEmail L'adresse email du destinataire.
     * @param string $templateCode
     * @param array $params
     * @return bool True si l'email est envoyé, false sinon.
     * @throws DateMalformedStringException
     */
    public function send(string $recipientEmail, string $templateCode, array $params = []): bool
    {
        // Récupérer le template depuis la BDD
        $template = $this->templateRepository->findByCode($templateCode);

        if (!$template) {
            error_log("MailService Error: Template '{$templateCode}' not found.");
            return false;
        }

        $subject = $template->getSubject();
        $bodyHtml = $template->getBodyHtml();
        $bodyText = $template->getBodyText();

        foreach ($params as $key => $value) {
            $placeholder = '{' . $key . '}';
            $subject = str_replace($placeholder, $value, $subject);
            if ($bodyHtml) {
                $bodyHtml = str_replace($placeholder, $value, $bodyHtml);
            }
            if ($bodyText) {
                $bodyText = str_replace($placeholder, $value, $bodyText);
            }
        }

        // Envoyer l'email
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail);
            $this->mailer->isHTML(true);
            $this->mailer->Subject = $subject;
            $this->mailer->Body    = $bodyHtml;
            $this->mailer->AltBody = $bodyText;
            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mailer->ErrorInfo}");
            return false;
        }
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
        return $this->send($recipientEmail, 'password_reset', [
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
        return $this->send($recipientEmail, 'password_modified', [
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
                $recapHtml .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . number_format($prix, 2, ',', ' ') . ' €</strong></td>';
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
                    $recapHtml .= '<td style="border-bottom: 1px solid #ddd; text-align: right;"><strong>' . number_format($subtotal, 2, ',', ' ') . ' €</strong></td>';
                    $recapHtml .= '</tr>';
                }
            }
            $recapHtml .= '</table>';
        }

        $event = $reservation->getEventObject();
        $session = $event ? current(array_filter($event->getSessions(), fn($s) => $s->getId() === $reservation->getEventSession())) : null;

        // Logique pour le total à payer
        $totalAmount = $reservation->getTotalAmount();
        $totalAmountPaid = $reservation->getTotalAmountPaid();
        $totalAPayerLabel = '';
        $totalAPayerColor = 'black';
        $montantAAfficher = 0;

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
        $totalAPayerHtml = "<strong style=\"color: {$totalAPayerColor};\">{$totalAPayerLabel}</strong>";

        // On appelle la méthode générique avec les bons paramètres
        return $this->send($reservation->getEmail(), 'paiement_confirme', [
            'URLPATH' => 'https://' . $_SERVER['HTTP_HOST'],
            'prenom' => $reservation->getPrenom(),
            'token' => $reservation->getToken(),
            'IDreservation' => $reservation->getId(),
            'EventLibelle' => $event ? $event->getLibelle() : '',
            'DateEvent' => $session ? $session->getEventStartAt()->format('d/m/Y H:i') : '',
            'Piscine' => $event && $event->getPiscine() ? $event->getPiscine()->getLibelle() . '(' . $event->getPiscine()->getAdresse() . ')' : '',
            'ReservationNomPrenom' => $reservation->getPrenom() . ' ' . $reservation->getNom(),
            'Reservationmail' => $reservation->getEmail(),
            'Reservationtel' => $reservation->getPhone(),
            'ReservationNbTotalPlace' => $nbTotalPlace,
            'AffichRecapDetailPlaces' => $recapHtml,
            'TotalAPayer' => $totalAPayerHtml,
            'ReservationMontantTotal' => number_format($montantAAfficher / 100, 2, ',', ' ') . ' €',
            'SIGNATURE' => SIGNATURE ?? 'L\'équipe Aqua Reims Artistique'
        ]);
    }
}