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
    public function index(?string $search = null): void
    {
        $accueil = $this->repository->findDisplayed();

        $this->render('/gestion/accueil', $accueil, "Gestion de la page d'accueil");
    }

    #[Route('/gestion/accueil/list/{search}', name: 'app_gestion_accueil_list')]
    public function list(?string $search = null): void
    {
        if ($search === null || $search === "" || !ctype_digit($search)) {
            echo 'Displayed';
            $accueil = $this->repository->findDisplayed();
        } else {
            $searchValue = (int)$search;
            if ($searchValue == 0) {
                echo 'all';
                $accueil = $this->repository->findAll();
            } else {
                echo 'id';
                $accueil = $this->repository->findById($searchValue);
            }
        }
        print_r($accueil);

        $this->render('/gestion/accueil', $accueil, "Gestion de la page d'accueil");
    }

    #[Route('/gestion/edit/{id}', name: 'app_gestion_accueil_edit')]
    public function edit(?int $id = null): void
    {
        $accueil = $this->repository->findDisplayed();

        $this->render('/gestion/accueil', $accueil, "Gestion de la page d'accueil");
    }

}