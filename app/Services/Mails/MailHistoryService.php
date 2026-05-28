<?php

namespace app\Services\Mails;

use app\Models\Reservation\Reservation;
use app\Models\Reservation\ReservationMailSent;
use app\Repository\Mail\MailTemplateRepository;
use app\Repository\Reservation\ReservationMailSentRepository;
use app\Services\Log\Logger;
use Exception;
use RuntimeException;

class MailHistoryService
{

    private MailTemplateRepository $mailTemplateRepository;
    private ReservationMailSentRepository $reservationMailSentRepository;

    public function __construct(
        MailTemplateRepository $mailTemplateRepository,
        ReservationMailSentRepository $reservationMailSentRepository
    )
    {
        $this->mailTemplateRepository = $mailTemplateRepository;
        $this->reservationMailSentRepository = $reservationMailSentRepository;

    }


    /**
     * Enregistrer dans les logs les résultats de tentatives d'envois de mails qui ne concernent pas de réservation
     *
     */
    public function logMailSent(array $params = [], string $codeEventLog = 'unexpected.send_attempt', bool $sent = false): void
    {
        $code = 'mail.' . $codeEventLog . '.' . ($sent === true ? 'sent':'failed');
        Logger::get()->event($code, $params);
    }


    /**
     * Enregistre l'envoi d'un email pour une réservation.
     *
     * @param Reservation $reservation
     * @param string $templateMailCode
     * @param int $templateMailId
     * @return bool True si l'enregistrement a réussi, false sinon.
     */
    public function recordMailSentForReservation(Reservation $reservation, string $templateMailCode, int $templateMailId = 0): bool
    {
        if ($templateMailId == 0) {
            $template = $this->mailTemplateRepository->findByCode($templateMailCode);
            if (!$template) {
                Logger::get()->event('mail.template.missing', ['reservation_id' => $reservation->getId(), 'template_code' => $templateMailCode]);
                return false;
            }
            $templateMailId = $template->getId();
        }

        $mailSentRecord = new ReservationMailSent();
        $mailSentRecord->setReservation($reservation->getId())
                ->setMailTemplate($templateMailId)
                ->setSentAt(date('Y-m-d H:i:s'));

        try {
            $id = $this->reservationMailSentRepository->insert($mailSentRecord);
            if ($id <= 0) {
                throw new RuntimeException('Échec insertion mail sent record.');
            }
            return true;
        } catch (Exception $e) {
            Logger::get()->error('mail', 'record_failure', [
                'message' => 'Failed to insert mail sent record.',
                'reservation_id' => $reservation->getId(),
                'template_code' => $templateMailCode,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}