<?php
namespace app\Services\Reservation;

use app\Models\Reservation\Reservation;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationRepository;
use app\Services\Mails\MailService;
use PHPMailer\PHPMailer\Exception;
use Throwable;

final class ReservationFinalSummaryService
{
    private string $finalSummaryTemplate = 'final_summary';
    private ReservationRepository $reservationRepository;
    private MailTemplateRepository $mailTemplateRepository;
    private MailService $mailService;

    public function __construct(
        ReservationRepository $reservationRepository,
        MailTemplateRepository $mailTemplateRepository,
        MailService $mailService,
    ) {
        $this->reservationRepository = $reservationRepository;
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->mailService = $mailService;
    }

    /**
     * Envoie le récap final pour les réservations concernées pour lesquelles il n'a pas encore été envoyé
     *
     * @param int $limit
     * @return array
     */
    public function sendFinalEmail(int $limit = 100): array
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

            try {
                $ok = $this->sendForReservation($reservation);
                if ($ok) {
                    $sent++;
                } else {
                    $failed++;
                    $errors[] = ['reservationId' => $reservation->getId(), 'error' => 'send returned false'];
                }
                // Délai de 1 seconde entre chaque envoi pour respecter les limites SMTP
                sleep(1);
            } catch (Throwable $e) {
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

    /**
     * Envoie le mail récapitulatif final pour une seule réservation (avec PDF et log)
     *
     * @param Reservation $reservation
     * @return bool
     * @throws Exception
     */
    public function sendForReservation(Reservation $reservation): bool
    {
        try {
            return $this->mailService->send(
                $this->finalSummaryTemplate,
                ['reservation' => $reservation],
                $reservation->getEmail(),
                'reservation.' . $this->finalSummaryTemplate
            );

        } catch (Exception $e) {
            throw new Exception("Erreur lors de l'envoi du mail pour la réservation ID {$reservation->getId()} : " . $e->getMessage());
        }
    }
}
