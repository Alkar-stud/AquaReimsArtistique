<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/gestion', name: 'app_gestion')]
class GestionController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(false); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }
    public function index(): void
    {
        // On appelle la méthode render héritée
        $this->render('/gestion/gestion', [], 'Gestion');
    }
}
