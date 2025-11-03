<?php
namespace app\Services\Reservation;

use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailPrepareService;
use app\Services\Mails\MailService;

final class ReservationFinalSummaryService
{
    private MailTemplateRepository $mailTemplateRepository;

    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly MailPrepareService $mailPrepareService,
        private readonly MailService $mailService,
        MailTemplateRepository $mailTemplateRepository
    ) {
        $this->mailTemplateRepository = $mailTemplateRepository;
    }

    /**
     * Envoie le récap final pour une date de référence.
     * - Sélection: DATE(:ref) entre DATE(event_session.event_start_at) et DATE(COALESCE(event.close_registration_at, event_session.event_start_at))
     * - Limite contrôlée
     */
    public function sendFinalEmail(int $limit = 100): array
    {
        //On va chercher le template final_summary
        $finalSummaryEmail = $this->mailTemplateRepository->findByCode('final_summary');
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

            try {
                $ok = $this->mailPrepareService->sendReservationConfirmationEmail($reservation, 'final_summary');
                if ($ok) {
                    // Log d’envoi (comme dans le contrôleur de gestion)
                    $this->mailService->recordMailSent($reservation, 'final_summary');
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = ['reservationId' => $reservation->getId(), 'error' => 'send returned false'];
                }
            } catch (\Throwable $e) {
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
