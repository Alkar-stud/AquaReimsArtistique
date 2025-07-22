<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/maintenance', name: 'app_maintenance')]

class MaintenanceController extends AbstractController
{
    public function index(): void
    {
        // On appelle la méthode render héritée
        $this->render('maintenance', [], 'En maintenance...');
    }
}