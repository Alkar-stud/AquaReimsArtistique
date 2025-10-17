<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;

class GestionController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(false);
    }

    #[Route('/gestion', name: 'app_gestion_home')]
    public function index(?string $search = null): void
    {
        $this->render('/gestion/home', [], "Gestion - Accueil");
    }

}