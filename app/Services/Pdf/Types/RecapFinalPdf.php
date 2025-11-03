<?php

namespace app\Services\Pdf\Types;

use app\Repository\Reservation\ReservationRepository;
use app\Services\Pdf\BasePdf;
use app\Services\Pdf\PdfTypeInterface;
use app\Utils\BuildLink;
use app\Utils\QRCode;
use RuntimeException;

final readonly class RecapFinalPdf implements PdfTypeInterface
{
    public function build(array $data): BasePdf
    {
        define('EURO_SYMBOL', chr(128));

        $reservationId = $data['reservationId'];
        $reservationRepository = new ReservationRepository();
        $QRCode = new QRCode();
        // Récupérer les données
        $reservation = $reservationRepository->findById($reservationId, true, true, false, true);
        if (!$reservation) {
            throw new RuntimeException("Réservation non trouvée pour l'ID: $reservationId");
        }

        // Générer le QR code
        $buildLink = new BuildLink();
        $qrCodeUrl = $buildLink->buildResetLink('/entrance', $reservation->getToken());
        $qrCodePath = QRCode::generate($qrCodeUrl, 250, 10);

        $documentTitle = "Récapitulatif de votre réservation - " . $reservation->getEventObject()->getName() . " - " . $reservation->getEventSessionObject()->getSessionName();
        // Instancier BasePdf (le constructeur ajoute la 1ère page et l'en-tête)
        $pdf = new BasePdf(mb_convert_encoding($documentTitle, 'ISO-8859-1', 'UTF-8'), 'P', 'mm', 'A4');

        $pdf->SetFont('Arial', '', 10);
        if ($qrCodePath) {
            $qrCodeSize = 50;
            $x = ($pdf->GetPageWidth() - $qrCodeSize) / 2;

            $pdf->Image($qrCodePath, $x, $pdf->GetY(), $qrCodeSize, $qrCodeSize, 'PNG');
            @unlink($qrCodePath);

            $pdf->Ln($qrCodeSize + 2);

            // Texte centré sous le QR code
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 5, mb_convert_encoding('Montrez ce QR code pour faciliter votre entrée', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
            $pdf->SetFont('Arial', '', 10);
            $pdf->Ln(8);
        }
        $pdf->Cell(160, 6, 'Bonjour '. $reservation->getFirstName() . ',', 0, 0, 'L');
        $pdf->Ln(10);
        $pdf->Cell(160, 6, mb_convert_encoding('Vous avez réservé pour venir voir notre gala et nous vous en remercions.', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Ln();
        $pdf->Cell(56, 6,mb_convert_encoding('Nous vous attendons donc pour le', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(34, 6, mb_convert_encoding($data['params']['DateEvent'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(34, 6,mb_convert_encoding('à la piscine : ', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Ln(8);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(180, 6, mb_convert_encoding($reservation->getEventObject()->getPiscine()->getLabel() . ' située au ' . $reservation->getEventObject()->getPiscine()->getAddress(), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(8);
        $pdf->Cell(160, 6, mb_convert_encoding('Voici le récapitulatif de votre commande ARA-' . str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) . ',', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Ln(8);

// Informations de la réservation
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, mb_convert_encoding('Informations de contact', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);

        $pdf->Cell(40, 5, mb_convert_encoding('Nom et prénom :', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Cell(0, 5, mb_convert_encoding($data['params']['ReservationNameFirstname'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');

        $pdf->Cell(40, 5, mb_convert_encoding('Email :', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Cell(0, 5, mb_convert_encoding($data['params']['ReservationEmail'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');

        $pdf->Cell(40, 5, mb_convert_encoding('Téléphone :', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Cell(0, 5, mb_convert_encoding($data['params']['ReservationPhone'], 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');

        $pdf->Ln(5);

        // Détails des places (version texte pour le PDF)
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, mb_convert_encoding('Détails de la réservation', 'ISO-8859-1', 'UTF-8'), 0, 1, 'L');
        $pdf->SetFont('Arial', '', 10);

        // Parser le texte depuis AffichRecapDetailPlacesText
        $recapText = $data['params']['AffichRecapDetailPlacesText'];

        $recapText = mb_convert_encoding($recapText, 'ISO-8859-1', 'UTF-8');
        $recapText = str_replace(['€', '?'], chr(128), $recapText);
        $lines = explode("\n", $recapText);
        foreach ($lines as $line) {
            if (trim($line)) {
                $pdf->MultiCell(0, 5, trim($line), 0, 'L');
            }
        }

        $pdf->Ln(5);

        // Montant total
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(50, 7, mb_convert_encoding($data['params']['TotalAPayerText'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Cell(0, 7, number_format($reservation->getTotalAmountPaid()/100, 2, ',', ' ') . ' ' . chr(128), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Ln(10);

        $pdf->Cell(50, 7, mb_convert_encoding('Aqua Reims Artistique vous remercie et à bientôt...', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Ln(10);

        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(50, 7, mb_convert_encoding("N'imprimez ce mail que si nécessaire, vous pouvez montrer votre téléphone, ou nous donner votre nom ou numéro de réservation", 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 10);

        return $pdf; // Retourner le PDF rempli
    }
}