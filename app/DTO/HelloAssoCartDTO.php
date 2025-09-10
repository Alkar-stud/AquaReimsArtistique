<?php

namespace app\DTO;

class HelloAssoCartDTO
{
    public int $totalAmount = 0; //total en centimes
    public int $initialAmount = 0; //total initial en centimes (si plusieurs échéances)
    public string $itemName; //Nom du panier
    public string $backUrl;
    public string $errorUrl;
    public string $returnUrl;
    // avec firstName, lastName, email et country=FRA (ou autre)
    public array $payer = [
        'firstName' => '',
        'lastName'  => '',
        'email'     => '',
        'country'   => 'FRA'
    ];
    public bool $containsDonation = false;
    public array $metaData = [];
    public int $checkoutID;
    public int $orderId;

    public function getTotalAmount(): int { return $this->totalAmount; }
    public function getInitialAmount(): int { return $this->initialAmount; }
    public function getItemName(): string { return $this->itemName; }
    public function getBackUrl(): string { return $this->backUrl; }
    public function getErrorUrl(): string { return $this->errorUrl; }
    public function getReturnUrl(): string { return $this->returnUrl; }
    public function getPayer(): array { return $this->payer; }
    public function getContainsDonation(): bool { return $this->containsDonation; }
    public function getMetaData(): array { return $this->metaData; }
    public function getCheckoutID(): int { return $this->checkoutID; }
    public function getOrderID(): int { return $this->orderId; }


    public function setTotalAmount(int $totalAmount): void { $this->totalAmount = $totalAmount; }
    public function setInitialAmount(int $initialAmount): void { $this->initialAmount = $initialAmount; }
    public function setItemName(string $itemName): void {$this->itemName = $itemName; }
    public function setBackUrl(string $backUrl): void { $this->backUrl = $backUrl; }
    public function setErrorUrl(string $errorUrl): void { $this->errorUrl = $errorUrl; }
    public function setReturnUrl(string $returnUrl): void { $this->returnUrl = $returnUrl; }
    public function setPayer(array $payer): void { $this->payer = $payer; }
    public function setContainsDonation(bool $containsDonation): void { $this->containsDonation = $containsDonation; }
    public function setMetaData(array $metaData): void { $this->metaData = $metaData; }
    public function setCheckoutID(int $checkoutID): void { $this->checkoutID = $checkoutID; }
    public function setOrderID(int $orderId): void { $this->orderId = $orderId; }
}
