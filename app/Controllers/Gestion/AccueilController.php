<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\AccueilRepository;
use app\Repository\ConfigRepository;

class AccueilController extends AbstractController
{
    private AccueilRepository $repository;

    public function __construct()
    {
        parent::__construct(false);
        $this->repository = new AccueilRepository();
    }

    #[Route('/gestion/accueil', name: 'app_gestion_accueil')]
    public function index(): void
    {
        $accueil = $this->repository->findDisplayed();
        $this->render('/gestion/accueil', $accueil, "Gestion de la page d'accueil");
    }

}