<?php

namespace app\Services\Pdf\Types;

use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Pdf\BasePdf;
use app\Services\Pdf\PdfTypeInterface;
use RuntimeException;

final readonly class RecapReservationsPdf implements PdfTypeInterface
{    public function __construct(
    private EventQueryService     $eventQueryService,
    private ReservationRepository $reservationRepository
) {
}

    public function build(array $data): BasePdf
    {
        $sessionId = $data['sessionId'];
        $sortOrder = $data['sortOrder'];

        // Récupérer les données
        $session = $this->eventQueryService->findSessionById($sessionId);
        if (!$session) {
            throw new RuntimeException("Session non trouvée pour l'ID: $sessionId");
        }
        $reservations = $this->reservationRepository->findBySession($sessionId, false, null, null, null, true, true, $sortOrder);

        $documentTitle = "Récapitulatif des réservations - " . $session->getEventObject()->getName() . " - " . $session->getSessionName();
        // Instancier BasePdf (le constructeur ajoute la 1ère page et l'en-tête)
        $pdf = new BasePdf(mb_convert_encoding($documentTitle, 'ISO-8859-1', 'UTF-8'), 'L');

        // Définir la structure du tableau
        $headers = ['ID', 'Nom de la réservation', 'Réglé', 'Nb Place'];
        $widths = [8,50,20,16];

        // Calculer la marge gauche pour centrer le tableau
        $totalWidth = array_sum($widths);
        $leftMargin = ($pdf->GetPageWidth() - $totalWidth) / 2;

        // Dessiner l'en-tête du tableau pour la première fois, en positionnant le curseur
        $pdf->SetX($leftMargin);
        $pdf->drawTableHeader($pdf, $headers, $widths);
        $pdf->SetFont('Arial', '', 9); // Police pour le contenu du tableau
        $i = 0; //pour les couleurs 1 ligne / 2
        foreach ($reservations as $reservation) {
            // Vérifier si on a besoin d'une nouvelle page avant d'écrire la ligne
            // Le '6' correspond à la hauteur de la cellule
            if ($pdf->GetY() + 6 > $pdf->GetPageHeight() - 15) { // 15mm = marge du bas (Footer)
                $pdf->AddPage('L');
                $pdf->SetX($leftMargin);
                $pdf->drawTableHeader($pdf, $headers, $widths);
                // Réappliquer la police après l'en-tête
                $pdf->SetFont('Arial', '', 9);
            }

            $remainingAmount = $reservation->getTotalAmount() - $reservation->getTotalAmountPaid();
            $allPaid = ($remainingAmount <= 0)
                ? 'Oui'
                : number_format($remainingAmount / 100, 2, ',', ' ');

            //On remplit les colonnes, 1ère ligne correspondant à la réservation (ID, nom, état règlement, nb places de la commande).
            $pdf->SetX($leftMargin);
            //Couleur de fond en alternance.
            if ($i % 2 === 0) {
                $pdf->SetFillColor(255, 255, 255); // Blanc
            } else {
                $pdf->SetFillColor(200, 200, 200); // Gris
            }
            $pdf->Cell($widths[0], 6, $reservation->getId(), 1, 0, 'C', true);
            $pdf->Cell($widths[1], 6, mb_convert_encoding($reservation->getName() . ' ' . $reservation->getFirstName(), 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
            if ($allPaid !== 'Oui') {
                $pdf->SetTextColor(255, 0, 0);
                $pdf->SetFont('Arial','B',9);
                $pdf->Cell($widths[2], 6, $allPaid . ' ' . chr(128), 1, 0, 'C', true);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial','',9);
            } else {
                $pdf->Cell($widths[2], 6, $allPaid, 1, 0, 'C', true);
            }
            // Le nombre de places correspond au nombre de "détails" de la réservation
            $pdf->Cell($widths[3], 6, count($reservation->getDetails()), 1, 0, 'C', true);

            $pdf->Ln(); // Terminer la ligne principale de la réservation

            // Préparer et afficher les compléments groupés sous la réservation
            $groupedComplements = [];
            // On suppose que $reservation->getComplements() retourne un tableau d'objets
            // où chaque objet a une méthode getTarifObject() (retournant un objet Tarif)
            // et une méthode getQuantity().
            foreach ($reservation->getComplements() as $complementItem) {
                $tarif = $complementItem->getTarifObject();
                $quantity = $complementItem->getQty();

                if ($tarif && $quantity > 0) {
                    $tarifId = $tarif->getId();
                    if (!isset($groupedComplements[$tarifId])) {
                        $groupedComplements[$tarifId] = [
                            'name' => $tarif->getName(),
                            'quantity' => 0
                        ];
                    }
                    $groupedComplements[$tarifId]['quantity'] += $quantity;
                }
            }

            $indent = 5; // Indentation pour les lignes de complément (en mm)
            $complementLineHeight = 5; // Hauteur légèrement plus petite pour les lignes de détail

            if (!empty($groupedComplements)) {
                foreach ($groupedComplements as $complementData) {
                    // Vérifier si un saut de page est nécessaire avant d'écrire la ligne de complément
                    if ($pdf->GetY() + $complementLineHeight > $pdf->GetPageHeight() - 15) {
                        $pdf->AddPage('L');
                        $pdf->SetX($leftMargin);
                        $pdf->drawTableHeader($pdf, $headers, $widths); // Redessiner l'en-tête
                        $pdf->SetFont('Arial', '', 9);
                        // Réappliquer la couleur de fond pour le nouveau bloc de réservation sur la nouvelle page
                        if ($i % 2 === 0) {
                            $pdf->SetFillColor(255, 255, 255); // Blanc
                        } else {
                            $pdf->SetFillColor(225, 225, 225); // Gris
                        }
                    }

                    $pdf->SetX($leftMargin + $widths[0]); // Appliquer l'indentation
                    $complementText = mb_convert_encoding($complementData['name'], 'ISO-8859-1', 'UTF-8');

                    // Cellule pour le nom du complément, couvrant la largeur de la colonne Nom de la réservation (augmentée de l'indentation)
                    $pdf->Cell($widths[1] + $widths[2], $complementLineHeight, $complementText, 1, 0, 'L', true);
                    $pdf->Cell($widths[3], $complementLineHeight, $complementData['quantity'], 1, 0, 'C', true);


                    $pdf->Ln();
                }
            } else {
                // Si aucun complément, afficher une ligne "aucun complément"
                if ($pdf->GetY() + $complementLineHeight > $pdf->GetPageHeight() - 15) {
                    $pdf->AddPage('L');
                    $pdf->SetX($leftMargin);
                    $pdf->drawTableHeader($pdf, $headers, $widths);
                    $pdf->SetFont('Arial', '', 9);
                    if ($i % 2 === 0) {
                        $pdf->SetFillColor(255, 255, 255);
                    } else {
                        $pdf->SetFillColor(225, 225, 225);
                    }
                }
                $pdf->SetX($leftMargin + $widths[0]);
                $pdf->Cell($widths[1] + $widths[2], $complementLineHeight, mb_convert_encoding("(aucun complément)", 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
                $pdf->Cell($widths[3], $complementLineHeight, '', 1, 0, 'C', true);
                $pdf->Ln();
            }
            $i++;
        }
        return $pdf; // Retourner le PDF rempli
    }
}