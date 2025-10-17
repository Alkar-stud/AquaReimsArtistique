<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use DateTime;
use DateTimeInterface;

class ReservationMailSent extends AbstractModel
{
    private int $reservation;
    private ?Reservation $reservationObject = null;
    private int $mail_template;
    private ?object $mailTemplateObject = null;
    private DateTimeInterface $sent_at;

    // --- GETTERS ---
    public function getReservation(): int { return $this->reservation; }
    public function getReservationObject(): ?Reservation { return $this->reservationObject; }
    public function getMailTemplate(): int { return $this->mail_template; }
    public function getMailTemplateObject(): ?object { return $this->mailTemplateObject; }
    public function getSentAt(): DateTimeInterface { return $this->sent_at; }

    // --- SETTERS ---
    public function setReservation(int $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }
    public function setReservationObject(?Reservation $reservationObject): self
    {
        $this->reservationObject = $reservationObject;
        if ($reservationObject) {
            $this->reservation = $reservationObject->getId();
        }
        return $this;
    }
    public function setMailTemplate(int $mail_template): self
    {
        $this->mail_template = $mail_template;
        return $this;
    }
    public function setMailTemplateObject(?object $mailTemplateObject): self
    {
        $this->mailTemplateObject = $mailTemplateObject;
        return $this;
    }
    public function setSentAt(string $sent_at): self
    {
        $this->sent_at = new DateTime($sent_at);
        return $this;
    }

    /**
     * Convertit l'objet en tableau pour la réponse JSON.
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'mailTemplateId' => $this->getMailTemplate(),
            'mailTemplateName' => $this->getMailTemplateObject()?->getCode(),
            'sentAt' => $this->getSentAt()->format(DateTime::ATOM), // ISO 8601
        ];
    }

}
