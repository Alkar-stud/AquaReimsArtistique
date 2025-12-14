<?php

namespace app\Services\Pdf\Types;

use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Pdf\BasePdf;
use app\Services\Pdf\PdfTypeInterface;
use app\Services\Piscine\SeatingPlanService;
use app\Services\Reservation\ReservationQueryService;
use RuntimeException;

final class RecapPlacesA3Pdf implements PdfTypeInterface
{
    private EventQueryService     $eventQueryService;
    private ReservationRepository $reservationRepository;
    private ReservationQueryService $reservationQueryService;


    public function __construct(
        EventQueryService $eventQueryService,
        ReservationRepository $reservationRepository,
        ReservationQueryService $reservationQueryService,
    ) {
        $this->eventQueryService = $eventQueryService;
        $this->reservationQueryService = $reservationQueryService;
    }

    public function build(array $data): BasePdf
    {
        $sessionId = (int)($data['sessionId'] ?? 0);
        $eventSession = $this->eventQueryService->findSessionById($sessionId);
        if (!$eventSession) {
            throw new RuntimeException("Session non trouvée pour l'ID: $sessionId");
        }

        $event = $eventSession->getEventObject();
        $title = "Plan récapitulatif des places - " . $event->getName() . " - " . $eventSession->getSessionName();

        // A3 paysage
        $pdf = new BasePdf(mb_convert_encoding($title, 'ISO-8859-1', 'UTF-8'), 'P', 'mm', 'A3');
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->SetFont('Arial', '', 8);

        // Palette inspirée de `variables.css`
        $colors = [
            'pmr'          => [3, 59, 168],     // --ara-color-darkblue
            'vip'          => [250, 78, 154],   // --ara-color-darkpink
            'volunteer'    => [92, 130, 206],   // --ara-color-purple
            'reserved'     => [255, 0, 0],      // --ara-color-red,
            'open'         => [255, 255, 255],  // blanc
            'closed'       => [255, 255, 255],  // blanc
            'text'         => [0, 0, 0],
            'textInverse'  => [255, 255, 255],
        ];

        $zonesRepo = new PiscineGradinsZonesRepository();
        $zones = $zonesRepo->findByPiscineId($event->getPiscine()->getId());
        $seatingService = new SeatingPlanService();

        // Marges et taille des cellules réduites pour tenir sur une page
        $leftMargin = 20;
        $cellW = 8;      // largeur cellule
        $cellH = 6;      // hauteur cellule
        $gapX  = 1;      // espacement horizontal

        $legendItems = [
            ['PMR', $colors['pmr'], true],
            ['VIP', $colors['vip'], true],
            ['Bénévole', $colors['volunteer'], true],
            ['Libre', $colors['open'], true],
            ['Réservé', $colors['reserved'], true],
            ['Fermé', $colors['closed'], false],
        ];

        $pdf->SetLeftMargin($leftMargin);


        /**************************************************************/
        /*               Affichage des légendes                       */
        /**************************************************************/
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(30, 8, mb_convert_encoding('Légende : ', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L');

        $pdf->SetFont('Arial', '', 9);
        foreach ($legendItems as [$label, $fill, $hasText]) {
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->Cell(10, 6, '', 1, 0, 'L', true);
            $pdf->Cell(20, 6, mb_convert_encoding($label, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C');
        }

        $pdf->Ln(9);

        // Calcul des capacités de la page
        $usableW = $pdf->GetPageWidth() - 2 * $leftMargin;
        $maxColsPerRow = max(1, (int)floor(($usableW + $gapX) / ($cellW + $gapX)));

        /**************************************************************/
        /*               Affichage des zones                          */
        /**************************************************************/
        foreach ($zones as $zone) {
            //Si la zone est fermée, on ne l'affiche pas
            if (!$zone->isOpen()) {
                continue;
            }
            //On récupère le plan
            $plan = $seatingService->getZonePlan($zone);
            //Puis l'occupation
            $seatStates = $this->reservationQueryService->getSeatStates($sessionId);

            // Titre de zone
            //On calcule la largeur nécessaire nb cases * $cellW + l'espace entre les cell + la 1ère colonne
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell($plan['zone']['nbSeatsHorizontally'] * ($cellW+1)+4, $cellH+2, mb_convert_encoding("Zone " . $zone->getZoneName(), 'ISO-8859-1', 'UTF-8'), 1, 0, 'L');
            $pdf->Ln($cellH+3);

            $pdf->SetFont('Arial', '', 8);

            $rows = $plan['rows'];
            $cols = $plan['cols'];

            // Nombre de colonnes effectivement affichables
            $colsShown = min($cols, $maxColsPerRow);

            foreach ($rows as $row) {
                //1ère colonne avec le numéro du rang
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell(4,$cellH, $row['rank'], 1,0,'C', true);
                $pdf->Cell($gapX,$cellH);

                //On liste les places de la ligne
                for ($c = 0; $c < $colsShown; $c++) {
                    $seat = $row['seats'][$c] ?? null;

                    if (!$seat || !$seat['exists']) {
                        // Vide
                        $pdf->SetFillColor(255, 255, 255);
                        $pdf->Cell($cellW,$cellH,'', 0, 0, 'C', true);
                        continue;
                    }

                    // Choix des couleurs selon l'état
                    $fill = $colors['open'];
                    $textColor = $colors['text'];
                    if (!$seat['open']) {
                        $fill = $colors['closed'];
                    } elseif (!empty($seat['pmr'])) {
                        $fill = $colors['pmr'];
                        $textColor = $colors['textInverse'];
                    } elseif (!empty($seat['vip'])) {
                        $fill = $colors['vip'];
                        $textColor = $colors['textInverse'];
                    } elseif (!empty($seat['volunteer'])) {
                        $fill = $colors['volunteer'];
                        $textColor = $colors['textInverse'];
                    }
                    //Si le siège est occupé
                    if (array_key_exists($seat['id'], $seatStates)) {
                        $fill = $colors['reserved'];
                        $textColor = $colors['textInverse'];
                    }

                    // Couleur cellule
                    $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);

                    // Contenu cellule
                    $pdf->SetTextColor($textColor[0], $textColor[1], $textColor[2]);
                    if (!$seat['open']) {
                        // Place fermée: croix
                        $pdf->Cell($cellW,$cellH,'', 1, 0, 'C', true);
                    } else {
                        $label = substr($seat['label'], 1); // sans le 1er chiffre qui est le numéro du rang
                        $pdf->SetFont('Arial', '', 7);
                        $pdf->Cell($cellW,$cellH,mb_convert_encoding($label, 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);
                        $pdf->SetFont('Arial', '', 8);
                    }

                    $pdf->Cell($gapX,$cellH);
                }
                // Ligne suivante
                $pdf->ln($cellH+1);
            }
            //Ligne en plus entre chaque zone
            $pdf->ln(1);
        }

        return $pdf;
    }
}