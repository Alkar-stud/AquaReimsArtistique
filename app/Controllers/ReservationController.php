<?php
namespace app\Controllers;

use app\BaseController;
use app\Attributes\Route;

#[Route('/reservation', name: 'app_reservation')]
class ReservationController extends BaseController
{
    public function index()
    {
        $this->render('Réservation');
    }
}