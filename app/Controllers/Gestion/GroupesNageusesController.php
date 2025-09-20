<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Nageuse\GroupesNageuses;
use app\Repository\Nageuse\GroupesNageusesRepository;

#[Route('/gestion/groupes-nageuses', name: 'app_gestion_groupes_nageuses')]
class GroupesNageusesController extends AbstractController
{
    private GroupesNageusesRepository $repository;

    public function __construct()
    {
        parent::__construct(false);
        $this->repository = new GroupesNageusesRepository();
    }

    public function index(): void
    {
        $groupes = $this->repository->findAll();
        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/groupes_nageuses', [
            'groupes' => $groupes,
            'flash_message' => $flashMessage
        ], 'Gestion des groupes nageuses');
    }

    #[Route('/gestion/groupes-nageuses/add/{id}', name: 'app_gestion_groupes_nageuses_add')]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $groupe = new GroupesNageuses();
            $groupe->setLibelle($_POST['libelle'] ?? '')
                ->setCoach(isset($_POST['coach']) && $_POST['coach'] !== '' ? mb_convert_case($_POST['coach'], MB_CASE_TITLE, "UTF-8") : null)
                ->setIsActive(isset($_POST['is_active']))
                ->setOrder((int)($_POST['order'] ?? 0))
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->repository->insert($groupe);
            $this->flashMessageService->setFlashMessage('success', "Groupe ajouté");
            header('Location: /gestion/groupes-nageuses');
            exit;
        }
    }

    #[Route('/gestion/groupes-nageuses/update/{id}', name: 'app_gestion_groupes_nageuses_update')]
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $groupe = $this->repository->findById($id);
            if ($groupe) {
                $groupe->setLibelle($_POST['libelle'] ?? '')
                    ->setCoach(isset($_POST['coach']) && $_POST['coach'] !== '' ? mb_convert_case($_POST['coach'], MB_CASE_TITLE, "UTF-8") : null)
                    ->setIsActive(isset($_POST['is_active']))
                    ->setOrder((int)($_POST['order'] ?? 0));
                $this->repository->update($groupe);
                $this->flashMessageService->setFlashMessage('success', "Groupe modifié");
            }
            header('Location: /gestion/groupes-nageuses');
            exit;
        }
    }

    #[Route('/gestion/groupes-nageuses/delete/{id}', name: 'app_gestion_groupes_nageuses_delete')]
    public function delete($id)
    {
        $this->repository->delete((int)$id);
        $this->flashMessageService->setFlashMessage('success', "Groupe supprimé");
        header('Location: /gestion/groupes-nageuses');
        exit;
    }
}