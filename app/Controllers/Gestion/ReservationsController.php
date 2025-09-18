<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\FlashMessageService;

class ReservationsController extends AbstractController
{

    private FlashMessageService $flashMessageService;

    function __construct()
    {
        parent::__construct(false);
        $this->flashMessageService = new FlashMessageService();

    }

    #[Route('/gestion/reservations', name: 'app_gestion_reservations')]
    public function index(?string $search = null): void
    {


        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/reservations', [
            'flash_message' => $flashMessage
        ], "Gestion des réservations");
    }

}