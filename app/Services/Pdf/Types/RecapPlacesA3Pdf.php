<?php

namespace app\Services\Pdf\Types;

use app\Services\Event\EventQueryService;
use app\Services\Pdf\BasePdf;
use app\Services\Pdf\PdfTypeInterface;
use RuntimeException;

final readonly class RecapPlacesA3Pdf implements PdfTypeInterface
{
    public function __construct(
        private EventQueryService $eventQueryService,
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

        $documentTitle = "Récapitulatif des places - " . $session->getEventObject()->getName();
        // Instancier BasePdf (le constructeur ajoute la 1ère page et l'en-tête)
        $pdf = new BasePdf(mb_convert_encoding($documentTitle, 'ISO-8859-1', 'UTF-8'), 'L', 'mm', 'A3');

        return $pdf; // Retourner le PDF rempli
    }
}