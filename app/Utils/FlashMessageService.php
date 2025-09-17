<?php

namespace app\Utils;

class FlashMessageService
{
    private ?array $flashMessage;

    public function __construct()
    {
        $this->flashMessage = null;
    }

    public function getFlashMessage()
    {
        if ($this->checkIfFlashMessageExist()) {
            $this->flashMessage = $_SESSION['flash_message'];
        }
        return $this->flashMessage;
    }

    public function setFlashMessage(string $type, string $message): void
    {
        $_SESSION['flash_message'] = [
            'type' => $type,
            'message' => $message
        ];
    }

    private function checkIfFlashMessageExist(): bool
    {
        return isset($_SESSION['flash_message']);
    }

    public function unsetFlashMessage(): void
    {
        unset($_SESSION['flash_message']);
    }

}