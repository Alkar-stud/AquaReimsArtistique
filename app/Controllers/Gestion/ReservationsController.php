<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;

class ReservationsController extends AbstractController
{

    function __construct()
    {
        parent::__construct(false);

    }

    #[Route('/gestion/reservation', name: 'app_gestion_reservations')]
    public function index(?string $search = null): void
    {
        // Le template de base est une coquille vide.
        // Le contenu est chargé dynamiquement via les routes 'upcoming' et 'past'.
        $this->render('/gestion/reservation', [], "Gestion des réservations");
    }


}