<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/', name: 'app_home')]

class HomeController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }
    public function index(): void
    {
        // On appelle la méthode render héritée
        $this->render('home', [], 'Accueil');
    }
}
