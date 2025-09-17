<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Piscine\Piscines;
use app\Repository\Piscine\PiscinesRepository;
use app\Services\FlashMessageService;
use DateMalformedStringException;

#[Route('/gestion/piscines', name: 'app_gestion_piscines')]
class PiscinesController extends AbstractController
{
    private PiscinesRepository $repository;
    private FlashMessageService $flashMessageService;

    public function __construct()
    {
        parent::__construct(false); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
        $this->repository = new PiscinesRepository();
        $this->flashMessageService = new FlashMessageService();
    }

    // Affiche la liste des piscines
    public function index()
    {
        $piscines = $this->repository->findAll();
        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();
        // On appelle la méthode render héritée
        $this->render('/gestion/piscines', [
            'data' => $piscines,
            'flash_message' => $flashMessage
        ], 'Gestion des piscines');
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
            $this->flashMessageService->setFlashMessage('success', "Piscine ajoutée.");
            header('Location: /gestion/piscines');
            exit;
        }
    }

    // Met à jour une piscine
    /**
     * @throws DateMalformedStringException
     */
    #[Route('/gestion/piscines/update/{id}', name: 'app_gestion_piscines_update')]
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $piscine = $this->repository->findById((int)$id);
            if ($piscine) {
                $piscine->setLibelle($_POST['nom'] ?? '')
                    ->setAdresse($_POST['adresse'] ?? '')
                    ->setMaxPlaces((int)($_POST['capacity'] ?? 0))
                    ->setNumberedSeats(isset($_POST['numberedSeats']) && $_POST['numberedSeats'] === 'oui');
                $this->repository->update($piscine);
                $this->flashMessageService->setFlashMessage('success', 'Piscine ' . $piscine->getLibelle() . ' modifiée');
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
        $this->flashMessageService->setFlashMessage('success', "Piscine supprimée");
        header('Location: /gestion/piscines');
        exit;
    }
}