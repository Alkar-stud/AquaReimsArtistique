<?php

namespace app\Services\Pdf;

use Fpdf\Fpdf;

/**
 * Classe personnalisée pour la génération de PDF, étendant FPDF.
 * Permet de définir des en-têtes et pieds de page communs à tous les documents.
 */
class BasePdf extends Fpdf
{
    public string $title = 'Document';

    /**
     * Constructeur de la classe PDF personnalisée.
     *
     * @param string $title Le titre du document.
     * @param string $orientation Orientation de la page (P ou L).
     * @param string $unit Unité de mesure (pt, mm, cm, in).
     * @param array|string $size Format de la page (A3, A4, A5, Letter, Legal ou tableau [width, height]).
     */
    public function __construct(string $title = 'Document', string $orientation = 'P', string $unit = 'mm', array|string $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
        $this->title = $title;

        // Initialisations communes à tous les PDF
        $this->SetTitle($title);
        $this->AliasNbPages(); // Permet d'utiliser {nb} pour le nombre total de pages
        $this->AddPage();      // Ajoute la première page et appelle automatiquement Header()
    }

    // En-tête du document
    public function Header(): void
    {
        //Logo
        $this->Image('/var/www/AquaReimsArtistique/public/assets/images/logo-ARA.png',10,10,20,0,'');
        //Police Arial gras 15
        $this->SetFont('Arial','B',12);

        //Décalage à droite affichage de l'image
        $this->SetX(35);
        //Couleur de fond
        $this->SetFillColor(204,204,255);
        //Couleur du texte
        $this->SetTextColor(0,0,0);

        // --- Logique pour une hauteur de bloc de titre fixe (20mm) ---
        $titleCellWidth = $this->GetPageWidth() - $this->lMargin - $this->rMargin - (35 - $this->lMargin); // Largeur de la cellule de titre
        $nbLines = $this->calculateNbLines($titleCellWidth, $this->title);

        $fixedBlockHeight = 20; // La hauteur totale que vous souhaitez
        $lineHeight = 0;

        if ($nbLines > 0) {
            $lineHeight = $fixedBlockHeight / $nbLines;
        }

        // On utilise MultiCell avec la hauteur de ligne calculée
        $this->MultiCell(0, $lineHeight, $this->title, 1, 'C');

        // On se positionne à la fin du bloc de titre pour la suite du document
        $this->SetY($this->GetY() + ($fixedBlockHeight - ($nbLines * $lineHeight)));
        $this->Ln(5); // Marge après le titre
    }

    // Pied de page
    public function Footer(): void
    {
        // Positionnement à 1,5 cm du bas
        $this->SetY(-15);
        // Police Arial italique 8
        $this->SetFont('Arial', 'I', 8);
        // Numéro de page x/total
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    /**
     * Dessine l'en-tête du tableau des participants sur l'objet PDF fourni.
     *
     * @param BasePdf $pdf L'objet PDF sur lequel dessiner.
     */
    public function drawTableHeader(BasePdf $pdf, array $headers, array $widths): void
    {
        // Conversion de chaque titre en ISO-8859-1
        $headers = array_map(fn($h) => mb_convert_encoding($h, 'ISO-8859-1', 'UTF-8'), $headers);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetFillColor(150, 150, 150);
        $pdf->SetTextColor(0);

        foreach ($headers as $i => $header) {
            $pdf->Cell($widths[$i], 8, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
    }

    /**
     * Calcule le nombre de lignes qu'un texte occupera dans une cellule d'une largeur donnée.
     *
     * @param float $cellWidth La largeur de la cellule.
     * @param string $text Le texte à mesurer.
     * @return int Le nombre de lignes.
     */
    protected function calculateNbLines(float $cellWidth, string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $cw = &$this->CurrentFont['cw'];
        if ($cellWidth == 0) {
            $cellWidth = $this->w - $this->rMargin - $this->x;
        }

        $width = $this->GetStringWidth($text);
        // Si la largeur du texte est inférieure à celle de la cellule, il n'y a qu'une ligne.
        return $width > $cellWidth ? (int)ceil($width / $cellWidth) : 1;
    }

}