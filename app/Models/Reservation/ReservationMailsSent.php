<?php

namespace app\Models\Reservation;

use app\Models\MailTemplate;
use DateMalformedStringException;
use DateTime;
use DateTimeInterface;

class ReservationMailsSent
{
    private int $id;
    private int $reservation; // ID de la réservation
    private ?Reservations $reservationObject = null; // Objet Reservations lié
    private int $mail_template; // ID du template de mail
    private ?MailTemplate $mailTemplateObject = null; // Objet MailsTemplate lié
    private DateTimeInterface $sent_at;

    // --- GETTERS ---

    public function getId(): int
    {
        return $this->id;
    }

    public function getReservation(): int
    {
        return $this->reservation;
    }

    public function getReservationObject(): ?Reservations
    {
        return $this->reservationObject;
    }

    public function getMailTemplate(): int
    {
        return $this->mail_template;
    }

    public function getMailTemplateObject(): ?MailTemplate
    {
        return $this->mailTemplateObject;
    }

    public function getSentAt(): DateTimeInterface
    {
        return $this->sent_at;
    }


    // --- SETTERS ---

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setReservation(int $reservation): self
    {
        $this->reservation = $reservation;
        return $this;
    }

    public function setReservationObject(?Reservations $reservationObject): self
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

    public function setMailTemplateObject(?MailTemplate $mailTemplateObject): self
    {
        $this->mailTemplateObject = $mailTemplateObject;
        if ($mailTemplateObject) {
            $this->mail_template = $mailTemplateObject->getId();
        }
        return $this;
    }

    /**
     * @throws DateMalformedStringException
     */
    public function setSentAt(string $sent_at): self
    {
        $this->sent_at = new DateTime($sent_at);
        return $this;
    }

}