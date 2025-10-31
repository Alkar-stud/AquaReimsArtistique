<?php

namespace app\Services\Pdf\Types;

use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Pdf\BasePdf;
use app\Services\Pdf\PdfTypeInterface;
use RuntimeException;

final readonly class RecapEvenementPdf implements PdfTypeInterface
{
    public function __construct(
        private EventQueryService     $eventQueryService,
    ) {
    }

    public function build(array $data): BasePdf
    {
        $sessionId = $data['sessionId'];

        // Récupérer les données de la session
        $session = $this->eventQueryService->findSessionById($sessionId);
        if (!$session) {
            throw new RuntimeException("Session non trouvée pour l'ID: $sessionId");
        }

        $stats = $this->eventQueryService->getTarifStatsForEvent($session->getEventId());
        ksort($stats['sessions']);

/*
echo '<pre>';
print_r($stats);
die;
*/
        $documentTitle = "Récapitulatif du gala - " . $session->getEventObject()->getName();
        // Instancier BasePdf (le constructeur ajoute la 1ère page et l'en-tête)
        $pdf = new BasePdf(mb_convert_encoding($documentTitle, 'ISO-8859-1', 'UTF-8'), 'P');


        // Définir la structure du tableau
        $headersDetail = ['Places assises', 'Nb ticket', 'Nb personnes', 'Total'];
        $headersComplement = ['Compléments', 'Quantité', '', 'Total'];
        $widths = [50,25,25,25];
        $totalWidth = array_sum($widths);

        // Calculer la marge gauche pour centrer le tableau
        $totalWidth = array_sum($widths);
        $leftMargin = ($pdf->GetPageWidth() - $totalWidth) / 2;


        foreach ($stats['sessions'] as $sessionId => $stat) {
            //Dessiner le début pour la session
            $pdf->SetX($leftMargin);
            $pdf->drawTableHeader($pdf,[$stat['sessionName']], [$totalWidth]);

            // Dessiner l'en-tête du tableau pour les places assises
            $pdf->SetX($leftMargin);
            $pdf->drawTableHeader($pdf, $headersDetail, $widths);
            foreach ($stat['seated']['perTarif'] as $tarifId => $tarifStat) {
                $amount = number_format($tarifStat['amount'] / 100, 2, ',', ' ');
                $unitPrice = number_format($tarifStat['price'] / 100, 2, ',', ' ');
                $pdf->SetX($leftMargin);
                $pdf->SetFont('Arial', '', 10);
                $pdf->Cell($widths[0], 6, mb_convert_encoding($tarifStat['name'], 'ISO-8859-1', 'UTF-8') . ' (' . $unitPrice . ' ' . chr(128) . ')', 1, 0, 'L', true);
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell($widths[1], 6, $tarifStat['tickets'], 1, 0, 'C', true);
                $pdf->Cell($widths[2], 6, $tarifStat['persons'], 1, 0, 'C', true);
                $pdf->Cell($widths[3], 6, $amount . ' ' . chr(128), 1, 0, 'C', true);
                $pdf->Ln();
            }
            //Sous-total pour les places assises
            $amount = number_format($stat['seated']['totals']['amount'] / 100, 2, ',', ' ');
            $pdf->SetX($leftMargin);
            $pdf->SetFillColor(150, 150, 150);
            $pdf->Cell($widths[0], 6, 'Sous-total', 1, 0, 'C', true);
            $pdf->SetFillColor(255);
            $pdf->Cell($widths[1], 6, $stat['seated']['totals']['tickets'], 1, 0, 'C', true);
            $pdf->Cell($widths[2], 6, $stat['seated']['totals']['persons'], 1, 0, 'C', true);
            $pdf->Cell($widths[3], 6, $amount . ' ' . chr(128), 1, 0, 'C', true);
            $pdf->Ln();


            // Dessiner l'en-tête du tableau pour les compléments
            $pdf->SetX($leftMargin);
            $pdf->drawTableHeader($pdf, $headersComplement, $widths);
            foreach ($stat['complements']['perTarif'] as $tarifId => $tarifStat) {
                $amount = number_format($tarifStat['amount'] / 100, 2, ',', ' ');
                $unitPrice = number_format($tarifStat['price'] / 100, 2, ',', ' ');
                $pdf->SetX($leftMargin);
                $pdf->Cell($widths[0], 6, mb_convert_encoding($tarifStat['name'], 'ISO-8859-1', 'UTF-8') . ' (' . $unitPrice . ' ' . chr(128) . ')', 1, 0, 'L', true);
                $pdf->Cell($widths[1], 6, $tarifStat['qty'], 1, 0, 'C', true);
                $pdf->Cell($widths[2], 6, '', 1, 0, 'C', true);
                $pdf->Cell($widths[3], 6, $amount . ' ' . chr(128), 1, 0, 'C', true);
                $pdf->Ln();
            }
            //Sous-total pour les compléments
            $amount = number_format($stat['complements']['totals']['amount'] / 100, 2, ',', ' ');
            $pdf->SetX($leftMargin);
            $pdf->SetFillColor(150, 150, 150);
            $pdf->Cell($widths[0], 6, 'Sous-total', 1, 0, 'C', true);
            $pdf->SetFillColor(255);
            $pdf->Cell($widths[1], 6, $stat['complements']['totals']['qty'], 1, 0, 'C', true);
            $pdf->Cell($widths[2], 6, '', 1, 0, 'C', true);
            $pdf->Cell($widths[3], 6, $amount . ' ' . chr(128), 1, 0, 'C', true);
            $pdf->Ln(12); //12 pour 2 * la hauteur de la dernière cellule imprimée qui est de 6.

        }

        // Dessiner l'en-tête du tableau des totaux
        $pdf->SetX($leftMargin);
        $pdf->drawTableHeader($pdf,['Total sur le gala'], [$totalWidth]);

        //1ère ligne
        $pdf->SetX($leftMargin);
        $pdf->SetFillColor(150, 150, 150);
        $pdf->Cell(45, 6, mb_convert_encoding('Nombre total de personnes', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $pdf->Cell(45, 6, mb_convert_encoding('Nombre total de compléments', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $pdf->Ln();

        //2ème ligne
        $pdf->SetX($leftMargin);
        $pdf->SetFillColor(255);
        $pdf->Cell(45, 6, $stats['eventTotals']['seated']['persons'], 1, 0, 'C', true);
        $pdf->Cell(45, 6, $stats['eventTotals']['complements']['qty'], 1, 0, 'C', true);
        $pdf->Ln();

        //3ème ligne
        $pdf->SetX($leftMargin);
        $pdf->SetFillColor(150, 150, 150);
        $pdf->Cell(45, 6, mb_convert_encoding('Montant total places assises', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $pdf->Cell(45, 6, mb_convert_encoding('Montant total compléments', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $pdf->Cell(35, 6, mb_convert_encoding('Montant total', 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
        $pdf->Ln();

        //4ème ligne
        $totalAmountSeated = number_format($stats['eventTotals']['seated']['amount'] / 100, 2, ',', ' ');
        $totalAmountComplements = number_format($stats['eventTotals']['complements']['amount'] / 100, 2, ',', ' ');
        $grandTotalAmount = number_format($stats['eventTotals']['grandTotal']['amount'] / 100, 2, ',', ' ');
        $pdf->SetX($leftMargin);
        $pdf->SetFillColor(255);
        $pdf->Cell(45, 6, $totalAmountSeated . ' ' . chr(128), 1, 0, 'C', true);
        $pdf->Cell(45, 6, $totalAmountComplements . ' ' . chr(128), 1, 0, 'C', true);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(35, 6, $grandTotalAmount . ' ' . chr(128), 1, 0, 'C', true);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Ln();

        return $pdf; // Retourner le PDF rempli
    }
}