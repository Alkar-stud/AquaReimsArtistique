<?php
namespace app\Controllers;

use app\Attributes\Route;
// REMPLACER : use app\BaseControllerBak;

#[Route('/login', name: 'app_login')]
// REMPLACER : class LoginController extends BaseControllerBak
class LoginController extends AbstractController // HÉRITE DU BON CONTRÔLEUR
{
    public function index()
    {
        // CORRIGER : $this->render('Login');
        // On utilise la nouvelle signature de render()
        // Le premier paramètre est le nom du fichier de vue (sans .html.php)
        $this->render('login', [], 'Connexion');
    }
}