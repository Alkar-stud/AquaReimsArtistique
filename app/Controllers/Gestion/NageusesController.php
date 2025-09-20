<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Nageuse\Nageuses;
use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;

#[Route('/gestion/nageuses', name: 'app_gestion_nageuses')]
class NageusesController extends AbstractController
{
    private NageusesRepository $repository;

    public function __construct()
    {
        parent::__construct(false);
        $this->repository = new NageusesRepository();
    }

    #[Route('/gestion/nageuses/add', name: 'app_gestion_nageuses_add')]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nageuse = new Nageuses();
            $nageuse->setName(mb_convert_case($_POST['name'], MB_CASE_TITLE, "UTF-8") ?? '')
                ->setGroupe(isset($_POST['groupe']) ? (int)$_POST['groupe'] : null)
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->repository->insert($nageuse);
            $this->flashMessageService->setFlashMessage('success', "Nageuse ajoutée");
            header('Location: /gestion/nageuses/' . $nageuse->getGroupe());
            exit;
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Erreur lors de l'ajout");
		}
    }

    #[Route('/gestion/nageuses/update/{id}', name: 'app_gestion_nageuses_update')]
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nageuse = $this->repository->findById($id);
            if ($nageuse) {
                $nageuse->setName(mb_convert_case($_POST['name'], MB_CASE_TITLE, "UTF-8") ?? '')
                    ->setGroupe(isset($_POST['groupe']) ? (int)$_POST['groupe'] : null);
                $this->repository->update($nageuse);
                $this->flashMessageService->setFlashMessage('success', "Nageuse modifiée");
                isset($_POST['origine-groupe']) ? $origineGroupe = $_POST['origine-groupe']:$origineGroupe = $nageuse->getGroupe();
            } else {
                $this->flashMessageService->setFlashMessage('danger', "Erreur lors de la modification");
            }
            header('Location: /gestion/nageuses/' . $origineGroupe);
            exit;
        }
    }

    #[Route('/gestion/nageuses/delete/{id}', name: 'app_gestion_nageuses_delete')]
    public function delete($id)
    {
        $nageuse = $this->repository->findById($id);
        $this->repository->delete((int)$id);
        $this->flashMessageService->setFlashMessage('success', "Nageuse supprimée");
        header('Location: /gestion/nageuses/' . $nageuse->getGroupe());
        exit;
    }
    //à la fin pour que la route avec add soit prise en compte
    #[Route('/gestion/nageuses/{groupId}', name: 'app_gestion_nageuses_group')]
    public function index($groupId): void
    {
        if ($groupId === 'all') {
            $nageuses = $this->repository->findAll();
            $titre = 'Toutes les nageuses';
            $groupeLibelle = null;
        } elseif (is_numeric($groupId)) {
            $nageuses = $this->repository->findByGroupeId((int)$groupId);
            $groupe = (new GroupesNageusesRepository())->findById((int)$groupId);
            $groupeLibelle = $groupe?->getLibelle();
            $titre = $groupeLibelle ? "Nageuses du groupe « $groupeLibelle »" : "Nageuses du groupe";
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Groupe invalide");
            header('Location: /gestion/groupes-nageuses');
            exit;
        }
        $groupes = (new GroupesNageusesRepository())->findAll();
        // Récupérer le message flash s'il existe
        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/nageuses', [
            'nageuses' => $nageuses,
            'groupId' => $groupId,
            'groupeLibelle' => $groupeLibelle,
            'groupes' => $groupes,
            'flash_message' => $flashMessage
        ], $titre);
    }

}
