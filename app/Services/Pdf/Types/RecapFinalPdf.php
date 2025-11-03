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
        $qrCodeUrl = $buildLink->buildResetLink('/modifData', $reservation->getToken());
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
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 5, mb_convert_encoding('Montrez ce QR code pour faciliter votre entrée', 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
            $pdf->Ln(8);
        }
        $pdf->Cell(160, 6, 'Bonjour '. $reservation->getFirstName() . ',', 0, 0, 'R');
        $pdf->Ln();
        $pdf->Cell(160, 6, mb_convert_encoding('Voici le récapitulatif de votre commande ARA-' . str_pad($reservation->getId(), 5, '0', STR_PAD_LEFT) . ',', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');
        $pdf->Ln();

        return $pdf; // Retourner le PDF rempli
    }
}