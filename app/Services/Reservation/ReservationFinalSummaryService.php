<?php
namespace app\Services\Reservation;

use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailPrepareService;
use app\Services\Mails\MailService;
use app\Services\Pdf\PdfGenerationService;
use Throwable;

final class ReservationFinalSummaryService
{
    private string $finalSummaryTemplate = 'final_summary';
    private ReservationRepository $reservationRepository;
    private MailTemplateRepository $mailTemplateRepository;
    private MailPrepareService $mailPrepareService;
    private PdfGenerationService $pdfGenerationService;
    private MailService $mailService;

    public function __construct(
        ReservationRepository $reservationRepository,
        MailTemplateRepository $mailTemplateRepository,
        MailPrepareService $mailPrepareService,
        PdfGenerationService $pdfGenerationService,
        MailService $mailService,
    ) {
        $this->reservationRepository = $reservationRepository;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->pdfGenerationService = $pdfGenerationService;
        $this->mailPrepareService = $mailPrepareService;
        $this->mailService = $mailService;
    }

    /**
     * Envoie le récap final pour les réservations concernées pour lesquelles il n'a pas encore été envoyé
     *
     * @param int $limit
     * @param bool $withQRCode
     * @return array
     */
    public function sendFinalEmail(int $limit = 100, bool $withQRCode = false): array
    {
        //On va chercher le template final_summary
        $finalSummaryEmail = $this->mailTemplateRepository->findByCode($this->finalSummaryTemplate);
        if (!$finalSummaryEmail) {
            return ['success' => false, 'message' => 'Template non trouvé'];
        }

        //On va chercher toutes les réservations concernées dont le template n'a pas encore été envoyé
        $reservations = $this->reservationRepository->findForFinalRecap($limit, $finalSummaryEmail->getId());

        $sent = 0;
        $failed = 0;
        $errors = [];

        foreach ($reservations as $reservation) {
            // Liste des IDs de templates déjà envoyés pour cette réservation
            $sentTemplateIds = array_map(
                static fn($ms) => $ms->getMailTemplate(),
                $reservation->getMailSent() ?? []
            );
            //On vérifie si le mail n'as pas encore été envoyé
            if (in_array($finalSummaryEmail->getId(), $sentTemplateIds, true)) {
                // Déjà envoyé -> on passe
                continue;
            }

            //On récupère le texte à mettre dans le mail
            $params = $this->mailPrepareService->buildReservationEmailParams($reservation, true);

            //On génère le PDF à mettre en PJ et on récupère le binaire pour ensuite l'attacher au mail
            $pdf = $this->pdfGenerationService->generateUnitPdf('RecapFinal', $reservation->getId(), $params);

            // Créer un fichier temporaire
            $pdfPath = sys_get_temp_dir() . '/recap_' . $reservation->getId() . '_' . uniqid() . '.pdf';
            file_put_contents($pdfPath, $pdf->Output('S'));
            try {
                $ok = $this->mailPrepareService->sendReservationConfirmationEmail($reservation, 'final_summary',$pdfPath);
                if ($ok) {
                    // Log d’envoi (comme dans le contrôleur de gestion)
                    $this->mailService->recordMailSent($reservation,'final_summary');

                    // Nettoyer le fichier temporaire du PDF après envoi
                    if ($pdfPath && is_file($pdfPath)) {
                        @unlink($pdfPath);
                    }
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = ['reservationId' => $reservation->getId(), 'error' => 'send returned false'];
                }
            } catch (Throwable $e) {
                // Nettoyage en cas d'erreur
                if (is_file($pdfPath)) {
                    @unlink($pdfPath);
                }
                $failed++;
                $errors[] = ['reservationId' => $reservation->getId(), 'error' => $e->getMessage()];
            }
        }

        return [
            'attempted' => count($reservations),
            'sent' => $sent,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}
