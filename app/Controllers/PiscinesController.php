<?php

namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\PiscinesRepository;
use app\Models\Piscines;
use DateMalformedStringException;

#[Route('/gestion/piscines', name: 'app_gestion_piscines')]
class PiscinesController extends AbstractController
{
    private PiscinesRepository $repository;

    public function __construct()
    {
        parent::__construct(false); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
        $this->repository = new PiscinesRepository();
    }

    // Affiche la liste des piscines
    public function index()
    {
        $piscines = $this->repository->findAll();
        // On appelle la méthode render héritée
        $this->render('/gestion/piscines', $piscines, 'Gestion des piscines');
    }

    // Ajoute une piscine
    #[Route('/gestion/piscines/add', name: 'app_gestion_piscines_add')]
    public function add()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $piscine = new Piscines();
            $piscine->setLibelle($_POST['nom'] ?? '')
                ->setAdresse($_POST['adresse'] ?? '')
                ->setMaxPlaces((int)($_POST['capacity'] ?? 0))
                ->setNumberedSeats(isset($_POST['numberedSeats']) && $_POST['numberedSeats'] === 'oui');

            $this->repository->insert($piscine);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Piscine ajoutée'];
            header('Location: /gestion/piscines');
            exit;
        }
    }

    // Met à jour une piscine
    /**
     * @throws DateMalformedStringException
     */
    #[Route('/gestion/piscines/update/{id}', name: 'app_gestion_piscines_update')]
    public function update(int $id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $piscine = $this->repository->findById((int)$id);
            if ($piscine) {
                $piscine->setLibelle($_POST['nom'] ?? '')
                    ->setAdresse($_POST['adresse'] ?? '')
                    ->setMaxPlaces((int)($_POST['capacity'] ?? 0))
                    ->setNumberedSeats(isset($_POST['numberedSeats']) && $_POST['numberedSeats'] === 'oui');
                $this->repository->update($piscine);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Piscine ' . $piscine->getLibelle() . ' modifiée'];
            }
            header('Location: /gestion/piscines');
            exit;
        }
    }

    // Supprime une piscine
    #[Route('/gestion/piscines/delete/{id}', name: 'app_gestion_piscines_delete')]
    public function delete($id)
    {
        $this->repository->delete((int)$id);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Piscine supprimée'];
        header('Location: /gestion/piscines');
        exit;
    }
}