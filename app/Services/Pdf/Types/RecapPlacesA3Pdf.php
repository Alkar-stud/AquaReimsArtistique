<?php

namespace app\Services\Pdf\Types;

use app\Services\Pdf\BasePdf;
use app\Services\Pdf\PdfTypeInterface;

class RecapPlacesA3Pdf implements PdfTypeInterface
{

    public function build(array $data): BasePdf
    {

        // Instancier BasePdf (le constructeur ajoute la 1ère page et l'en-tête)
        $pdf = new BasePdf('', 'L');

        return $pdf; // Retourner le PDF rempli
    }
}