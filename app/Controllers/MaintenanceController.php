<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/maintenance', name: 'app_maintenance')]

class MaintenanceController extends AbstractController
{
    public function index(): void
    {
        //Si le user est role <= 1, il peut accéder à la page /gestion
        if (isset($_SESSION['user']['role']['level']) && $_SESSION['user']['role']['level'] <= 1 && $_SERVER['REQUEST_URI'] == '/gestion') {
            $this->render('gestion/gestion', [], 'gestion');
            exit;
        } else {
            // On appelle la méthode render héritée
            $this->render('maintenance', [], 'En maintenance...');
        }
    }
}