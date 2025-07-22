<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/', name: 'app_home')]

class HomeController extends AbstractController
{
    public function index(): void
    {
        // On appelle la méthode render héritée
        $this->render('home', [], 'Accueil');
    }
}