<?php
namespace app\Controllers;

use app\BaseController;
use app\Attributes\Route;

#[Route('/', name: 'app_home')]

class HomeController extends BaseController
{
    public function index()
    {
        $this->render('Accueil');
    }
}