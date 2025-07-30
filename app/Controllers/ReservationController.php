<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }

    public function index()
    {
        $this->render('reservation', [], 'Réservation');
    }
}