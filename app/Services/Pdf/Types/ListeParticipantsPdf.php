<?php

namespace app\Services\Pdf\Types;

use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Pdf\BasePdf;
use app\Services\Pdf\PdfTypeInterface;
use RuntimeException;

final readonly class ListeParticipantsPdf implements PdfTypeInterface
{
    public function __construct(
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

        $documentTitle = "Liste des participants - " . $session->getEventObject()->getName() . " - " . $session->getSessionName();
        // Instancier BasePdf (le constructeur ajoute la 1ère page et l'en-tête)
        $pdf = new BasePdf(mb_convert_encoding($documentTitle, 'ISO-8859-1', 'UTF-8'));

        // Définir la structure du tableau
        $headers = ['ID', 'Nom de la réservation', 'Réglé', 'Nb Place'];
        $widths = [8,50,20,16];

        if (!empty($reservations) && $reservations[0]->getEventObject()->getPiscine()->getNumberedSeats()) {
            $headers[] = 'Emplacement';
            $widths[] = 21;
        }

        // Calculer la marge gauche pour centrer le tableau
        $totalWidth = array_sum($widths);
        $leftMargin = ($pdf->GetPageWidth() - $totalWidth) / 2;

        // Dessiner l'en-tête du tableau pour la première fois, en positionnant le curseur
        $pdf->SetX($leftMargin);
        $pdf->drawTableHeader($pdf, $headers, $widths);

        $pdf->SetFont('Arial', '', 9);
        foreach ($reservations as $reservation) {
            // Vérifier si on a besoin d'une nouvelle page avant d'écrire la ligne
            // Le '6' correspond à la hauteur de la cellule
            if ($pdf->GetY() + 6 > $pdf->GetPageHeight() - 15) { // 15mm = marge du bas (Footer)
                $pdf->AddPage('P');
                $pdf->SetX($leftMargin);
                $pdf->drawTableHeader($pdf, $headers, $widths);
                $pdf->SetFont('Arial', '', 9);
            }

            $remainingAmount = $reservation->getTotalAmount() - $reservation->getTotalAmountPaid();
            $allPaid = ($remainingAmount <= 0)
                ? 'Oui'
                : number_format($remainingAmount / 100, 2, ',', ' ');

            //On remplit les colonnes, 1ère ligne correspondant à la réservation (ID, nom, état règlement, nb places de la commande).
            $pdf->SetX($leftMargin);
            //Couleur de fond.
            $pdf->SetFillColor(200,200,200);
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


            $pdf->Ln();
            $pdf->SetX($leftMargin);
            // Les lignes suivantes correspondent aux noms/prénoms des participants,
            // du tarif (utilise par exemple pour contrôler visuellement le tarif enfant à l'accueil) et du numéro de place si besoin.
            $pdf->SetFillColor(255, 255, 255); // Couleur de fond pour les lignes de participants
            foreach ($reservation->getDetails() as $participant) {
                //1ère colonne vide
                $pdf->Cell($widths[0], 6, '', 1, 0);
                //2ème colonne avec NomPrenom
                $pdf->Cell($widths[1], 6, mb_convert_encoding($participant->getName() . ' ' . $participant->getFirstName(), 'ISO-8859-1', 'UTF-8'), 1, 0, 'L', true);
                //3ème colonne avec le type de tarif
                $pdf->Cell(($widths[2]+$widths[3]), 6, 'Tarif : ' . mb_convert_encoding($participant->getTarifObject()->getName(), 'ISO-8859-1', 'UTF-8'), 1, 0, 'C', true);

                //4ème avec le numéro de la place si requis
                if ($reservation->getEventObject()->getPiscine()->getNumberedSeats()) {
                    $pdf->Cell($widths[4], 6, $participant->getPlaceObject()->getFullPlaceName(), 1, 0, 'C', true);
                }

                $pdf->Ln();
                $pdf->SetX($leftMargin);
            }
        }

        // Retourner le PDF rempli
        return $pdf;
    }

}