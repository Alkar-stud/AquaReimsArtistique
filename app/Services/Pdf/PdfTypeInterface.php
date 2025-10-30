<?php

namespace app\Services\Pdf;

interface PdfTypeInterface
{
    /**
     * Construit et retourne un objet PDF.
     *
     * @param array $data Données nécessaires à la construction (ex: ['sessionId' => 1, 'sortOrder' => 'IDreservation'])
     * @return BasePdf L'objet PDF finalisé.
     */
    public function build(array $data): BasePdf;

}