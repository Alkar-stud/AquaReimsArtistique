<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Tarif\TarifRepository;
use app\Services\Csv\BaseCsv;
use app\Services\Log\Logger;
use app\Services\Pdf\PdfGenerationService;
use app\Services\Reservation\ReservationQueryService;
use app\Utils\DataHelper;
use app\Utils\StringHelper;
use Exception;

class ExportsController extends AbstractController
{
    private PdfGenerationService $PdfGenerationService;
    private DataHelper $dataHelper;
    private ReservationQueryService $reservationQueryService;

    public function __construct(
        PdfGenerationService $PdfGenerationService,
        DataHelper $dataHelper,
        ReservationQueryService $reservationQueryService,
    )
    {
        parent::__construct(false);
        $this->PdfGenerationService = $PdfGenerationService;
        $this->dataHelper = $dataHelper;
        $this->reservationQueryService = $reservationQueryService;
        $this->checkIfCurrentUserIsAllowedToManagedThis(3);
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
            //On log l'event
            Logger::get()->event(
                'application.admin.reservation.export.pdf.succeeded',
            [
                'session' => $sessionId,
                'paramètres : ' => $pdfType
            ]);
            exit;
        } catch (Exception $e) {
            //On log l'event
            Logger::get()->event(
                'application.admin.reservation.export.pdf.failed',
                [
                    'session' => $sessionId,
                    'error' => 'Erreur lors de la génération du PDF : ' . $e
                ]);
            http_response_code(404);
            die("Erreur lors de la génération du PDF : " . $e->getMessage());
        }
    }

    #[Route('/gestion/reservations/extract-csv', name: 'app_gestion_reservations_extract_csv', methods: ['POST'])]
    public function extractCsv(): void
    {
        $data = $this->dataHelper->getAndCheckPostData(['checkedFields', 'selectedTarif', 'id']);
        if ($data === null) { return; }

        $sessionId = (int)$data['id'];
        $checkedFields = $data['checkedFields'] ?? [];
        $selectedTarifIds = $data['selectedTarif'] ?? [];

        try {
            $reservations = $this->reservationQueryService
                ->getReservationsByTarifIds($selectedTarifIds, $checkedFields, $sessionId);

            // Préparer headerFields (déjà fourni par checkedFields)
            $headerFields = $checkedFields;

            // Récupération des noms des tarifs pour le slug
            $slugPart = 'session';
            if (!empty($selectedTarifIds) && !($selectedTarifIds === [0])) {
                $tarifRepo = new TarifRepository();
                $tarifs = $tarifRepo->findByIds(array_map('intval', $selectedTarifIds));
                $names = array_map(fn($t) => $t->getName(), $tarifs);
                $helper = new StringHelper();
                $slugPart = $helper->slugify(implode('-', array_slice($names, 0, 3)));
            }

            $filename = 'export_reservations_' . $slugPart . '_' . date('Ymd_His') . '.csv';

            // Génération mémoire
            $baseCsv = new BaseCsv();
            $csvContent = $baseCsv->generateContent($headerFields, $reservations);

            // Headers téléchargement
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Length: ' . strlen($csvContent));
            header('Content-Disposition: attachment; filename=' . $filename);
            header('Pragma: no-cache');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Expires: 0');

            echo $csvContent;
            Logger::get()->event(
                'application.admin.reservation.export.csv.succeeded',
            [
                'session' => $sessionId,
                'paramètres : ' => $checkedFields,
                'tarifs : ' => $selectedTarifIds
            ]);
            exit;
        } catch (Exception $e) {
            //On log l'event
            Logger::get()->event(
                'application.admin.reservation.export.csv.failed',
                [
                    'error' => 'Erreur lors de la génération du CSV : ' . $e,
                    'session' => $sessionId,
                    'paramètres : ' => $checkedFields,
                    'tarifs : ' => $selectedTarifIds
                ]);
            http_response_code(400);
            echo "Erreur lors de la génération du CSV : " . $e->getMessage();
        }
    }
}