<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends AbstractController
{
    public function index()
    {
        $this->render('reservation', [], 'Réservation');
    }
}