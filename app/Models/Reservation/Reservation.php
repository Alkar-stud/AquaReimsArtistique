<?php
namespace app\Models\Reservation;

use app\Models\AbstractModel;
use app\Models\Event\Event;
use app\Models\Event\EventSession;
use app\Models\Swimmer\Swimmer;
use app\Models\User\User;
use app\Utils\StringHelper;
use DateTime;
use DateTimeInterface;
use InvalidArgumentException;

class Reservation extends AbstractModel
{
    // FK vers event
    private int $event;
    private ?Event $eventObject = null;
    // FK vers event_session
    private int $event_session;
    private ?EventSession $eventSessionObject = null;
    private ?string $reservation_temp_id = null;
    private ?DateTimeInterface $rgpd_date_consentement = null;
    private string $name;
    private string $firstname;
    private string $email;
    private ?string $phone = null;

    // FK swimmer si limitation active
    private ?int $swimmer_if_limitation = null;
    private ?Swimmer $swimmer = null;

    private int $total_amount = 0;
    private int $total_amount_paid = 0;

    private ?string $token = null;
    private DateTimeInterface $token_expire_at;

    private bool $is_canceled = false;
    private bool $is_checked = false;

    private ?string $comments = null;
    private ?DateTimeInterface $complements_given_at = null;
    private ?int $complements_given_by = null;
    private ?User $complements_given_by_user = null;

    // Relations enfants
    private array $details = [];
    private array $complements = [];
    private array $payments = [];
    private array $mailSent = [];

    // --- GETTERS ---
    public function getEvent(): int { return $this->event; }
    public function getEventObject(): ?Event { return $this->eventObject; }
    public function getEventSession(): int { return $this->event_session; }
    public function getEventSessionObject(): ?EventSession { return $this->eventSessionObject; }
    public function getReservationTempId(): ?string { return $this->reservation_temp_id; }
    public function getName(): string { return $this->name; }
    public function getFirstName(): string { return $this->firstname; }
    public function getEmail(): string { return $this->email; }
    public function getPhone(): ?string { return $this->phone; }
    public function getSwimmerId(): ?int { return $this->swimmer_if_limitation; }
    public function getRgpdDateConsentement(): ?DateTimeInterface { return $this->rgpd_date_consentement; }
    public function getSwimmer(): ?Swimmer { return $this->swimmer; }
    public function getTotalAmount(): int { return $this->total_amount; }
    public function getTotalAmountPaid(): int { return $this->total_amount_paid; }
    public function getToken(): ?string { return $this->token; }
    public function getTokenExpireAt(): DateTimeInterface { return $this->token_expire_at; }
    public function isCanceled(): bool { return $this->is_canceled; }
    public function isChecked(): bool { return $this->is_checked; }
    public function getComments(): ?string { return $this->comments; }
    public function getComplementsGivenAt(): ?DateTimeInterface { return $this->complements_given_at; }
    public function getComplementsGivenBy(): ?int { return $this->complements_given_by; }
    public function getComplementsGivenByUser(): ?User { return $this->complements_given_by_user; }
    public function getDetails(): array { return $this->details; }
    public function getComplements(): array { return $this->complements; }
    public function getPayments(): array { return $this->payments; }
    public function getMailSent(): array { return $this->mailSent; }

    // --- SETTERS ---
    public function setEvent(int $event): self { $this->event = $event; return $this; }
    public function setEventObject(?Event $eventObject): self {
        $this->eventObject = $eventObject;
        if ($eventObject) { $this->event = $eventObject->getId(); }
        return $this;
    }

    public function setEventSession(int $event_session): self { $this->event_session = $event_session; return $this; }
    public function setEventSessionObject(?EventSession $eventSessionObject): self {
        $this->eventSessionObject = $eventSessionObject;
        if ($eventSessionObject) {
            $this->event_session = $eventSessionObject->getId();
            // Propager l'event si connu via la session
            if (method_exists($eventSessionObject, 'getEventId')) {
                $this->event = $eventSessionObject->getEventId();
            }
        }
        return $this;
    }

    public function setReservationTempId(?string $reservation_temp_id): self { $this->reservation_temp_id = $reservation_temp_id; return $this; }

    public function setName(string $name): self
    {
        $this->name = StringHelper::toUpperCase($name);
        return $this;
    }
    public function setFirstName(string $firstname): self
    {
        $this->firstname = StringHelper::toTitleCase($firstname);
        return $this;
    }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function setPhone(?string $phone): self { $this->phone = ($phone === '' ? null : $phone); return $this; }

    public function setSwimmerId(?int $swimmer_if_limitation): self { $this->swimmer_if_limitation = $swimmer_if_limitation; return $this; }
    public function setSwimmer(?Swimmer $swimmer): self {
        $this->swimmer = $swimmer;
        if ($swimmer) { $this->swimmer_if_limitation = $swimmer->getId(); }
        return $this;
    }
    public function setRgpdDateConsentement(DateTimeInterface|string|null $date): self
    {
        if (is_string($date) && $date !== '') {
            $this->rgpd_date_consentement = new DateTime($date);
        } elseif ($date instanceof DateTimeInterface) {
            $this->rgpd_date_consentement = $date;
        } else {
            $this->rgpd_date_consentement = null;
        }

        return $this;
    }
    public function setTotalAmount(int $total_amount): self { $this->total_amount = $total_amount; return $this; }
    public function setTotalAmountPaid(int $total_amount_paid): self { $this->total_amount_paid = $total_amount_paid; return $this; }

    public function setToken(?string $token): self { $this->token = $token; return $this; }
    public function setTokenExpireAt(string $token_expire_at): self {
        $this->token_expire_at = new DateTime($token_expire_at);
        return $this;
    }

    public function setIsCanceled(bool $is_canceled): self { $this->is_canceled = $is_canceled; return $this; }
    public function setIsChecked(bool $is_checked): self { $this->is_checked = $is_checked; return $this; }

    public function setComments(?string $comments): self { $this->comments = ($comments === '' ? null : $comments); return $this; }
    public function setComplementsGivenAt(?string $complements_given_at): self {
        $this->complements_given_at = $complements_given_at ? new DateTime($complements_given_at) : null;
        return $this;
    }
    public function setComplementsGivenBy(?int $complements_given_by): self {
        $this->complements_given_by = $complements_given_by;
        return $this;
    }

    public function setComplementsGivenByUser(?User $user): self {
        $this->complements_given_by_user = $user;
        if ($user !== null) {
            $this->complements_given_by = $user->getId();
        }
        return $this;
    }

    public function setDetails(array $details): self { $this->details = $details; return $this; }
    public function setComplements(array $complements): self { $this->complements = $complements; return $this; }
    public function setPayments(array $payments): self { $this->payments = $payments; return $this; }
    public function setMailSent(array $mailSent): self { $this->mailSent = $mailSent; return $this; }


    /**
     * Convertit l'objet en tableau pour la réponse JSON.
     * @return array
     */
    public function toArray(): array
    {
        $eventObject = $this->getEventObject();
        $eventSessionObject = $this->getEventSessionObject();

        return [
            'id' => $this->getId(),
            'reservationTempId' => $this->getReservationTempId(),
            'name' => $this->getName(),
            'firstName' => $this->getFirstName(),
            'email' => $this->getEmail(),
            'phone' => $this->getPhone(),
            'rgpdDateConsentement' => $this->getRgpdDateConsentement()?->format(DateTimeInterface::ATOM),
            'totalAmount' => $this->getTotalAmount(),
            'totalAmountPaid' => $this->getTotalAmountPaid(),
            'isCanceled' => $this->isCanceled(),
            'isChecked' => $this->isChecked(),
            'comments' => $this->getComments(),
            'token' => $this->getToken(),
            'tokenExpireAt' => $this->getTokenExpireAt()?->format(DateTimeInterface::ATOM),
            'event' => $eventObject ? [
                'id' => $eventObject->getId(),
                'name' => $eventObject->getName(),
                'piscineId' => $eventObject->getPlace() // Ajout de l'ID de la piscine
            ] : null,
            'eventSession' => $eventSessionObject ? ['id' => $eventSessionObject->getId(), 'name' => $eventSessionObject->getSessionName()] : null,
            'swimmer' => $this->getSwimmer() ? [
                'id' => $this->getSwimmer()->getId(),
                'name' => $this->getSwimmer()->getName()
            ] : null,
            'details' => array_map(fn($detail) => $detail->toArray(), $this->getDetails()),
            'complements' => array_map(fn($complement) => $complement->toArray(), $this->getComplements()),
            'payments' => array_map(fn($payment) => $payment->toArray(), $this->getPayments()),
            'mailSent' => array_map(fn($mail) => $mail->toArray(), $this->getMailSent()),
        ];
    }

}
