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
        $this->render('/gestion/groupes_nageuses', ['groupes' => $groupes], 'Gestion des groupes nageuses');
    }

    #[Route('/gestion/groupes-nageuses/add', name: 'app_gestion_groupes_nageuses_add')]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $groupe = new GroupesNageuses();
            $groupe->setLibelle($_POST['libelle'] ?? '')
                ->setCoach(isset($_POST['coach']) && $_POST['coach'] !== '' ? $_POST['coach'] : null)
                ->setIsActive(isset($_POST['is_active']))
                ->setOrder((int)($_POST['order'] ?? 0))
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->repository->insert($groupe);
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Groupe ajouté'];
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
                    ->setCoach(isset($_POST['coach']) && $_POST['coach'] !== '' ? $_POST['coach'] : null)
                    ->setIsActive(isset($_POST['is_active']))
                    ->setOrder((int)($_POST['order'] ?? 0));
                $this->repository->update($groupe);
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Groupe modifié'];
            }
            header('Location: /gestion/groupes-nageuses');
            exit;
        }
    }

    #[Route('/gestion/groupes-nageuses/delete/{id}', name: 'app_gestion_groupes_nageuses_delete')]
    public function delete($id)
    {
        $this->repository->delete((int)$id);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Groupe supprimé'];
        header('Location: /gestion/groupes-nageuses');
        exit;
    }
}