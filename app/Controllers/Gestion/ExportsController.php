<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Pdf\PdfGenerationService;
use Exception;

class ExportsController extends AbstractController
{
    private PdfGenerationService $PdfGenerationService;

    public function __construct(
        PdfGenerationService $PdfGenerationService,
    )
    {
        parent::__construct(false);
        $this->PdfGenerationService = $PdfGenerationService;
    }

    #[Route('/gestion/reservations/exports', name: 'app_gestion_reservations_exports', methods: ['GET'])]
    public function exports(): void
    {
        // Récupérer les paramètres depuis $_GET
        $sessionId = (int)($_GET['s'] ?? 0);
        $pdfType = $_GET['pdf'] ?? 'ListeParticipants';
        $sortOrder = $_GET['tri'] ?? 'IDreservation';

        try {
            // On construit le PDF en fonction de son type.
            $pdf = $this->PdfGenerationService->generate($pdfType, $sessionId, $sortOrder);

            // On envoie le PDF construit au navigateur.
            $pdf->Output('I', $this->PdfGenerationService->getFilenameForPdf($pdfType,$sessionId) . '.pdf');
            exit;
        } catch (Exception $e) {
            http_response_code(404);
            die("Erreur lors de la génération du PDF : " . $e->getMessage());
        }
    }
}