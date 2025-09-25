<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Swimmer\SwimmerGroup;
use app\Repository\Swimmer\SwimmerGroupRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Services\DataValidation\SwimmerGroupDataValidationService;

class SwimmersGroupsController extends AbstractController
{
    private SwimmerGroupRepository $swimmerGroupRepository;
    private SwimmerGroupDataValidationService $swimmerGroupDataValidationService;

    public function __construct()
    {
        parent::__construct(false);
        $this->swimmerGroupRepository = new SwimmerGroupRepository();
        $this->swimmerGroupDataValidationService = new SwimmerGroupDataValidationService();
    }

    #[Route('/gestion/swimmers-groups', name: 'app_gestion_swimmers_groups')]
    public function index(): void
    {
        if (isset($_GET['g']) && $_GET['g'] === 'all') {
            $onlyIsActive = false;
        } else {
            $onlyIsActive = true;
        }
        //On récupère les groupes de nageurs
        $swimmersGroups = $this->swimmerGroupRepository->findAll($onlyIsActive);

        $this->render('/gestion/swimmers_groups', [
                'data' => $swimmersGroups,
                'currentUser' => $this->currentUser
            ], 'Gestion des groupes de nageurs');
    }

    #[Route('/gestion/swimmers-groups/add', name: 'app_gestion_swimmers_groups_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'swimmers-groups');

        // Validation des données centralisée
        $error = $this->swimmerGroupDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/swimmers-groups');
        }

        $groupe = new SwimmerGroup();
        $groupe->setName($this->swimmerGroupDataValidationService->getName())
            ->setCoach($this->swimmerGroupDataValidationService->getCoach())
            ->setIsActive($this->swimmerGroupDataValidationService->getIsActive())
            ->setOrder($this->swimmerGroupDataValidationService->getOrder());
        $this->swimmerGroupRepository->insert($groupe);
        $this->flashMessageService->setFlashMessage('success', "Groupe ajouté");
        $this->redirect('/gestion/swimmers-groups');
    }

    #[Route('/gestion/swimmers-groups/update', name: 'app_gestion_swimmers_groups_update', methods: ['POST'])]
    public function update(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'swimmers-groups');

        //On récupère le groupe.
        $groupId = (int)($_POST['group_id'] ?? 0);
        $group = $this->swimmerGroupRepository->findById($groupId);

        if (!$group) {
            $this->flashMessageService->setFlashMessage('danger', "Groupe non trouvé.");
            $this->redirect('/gestion/swimmers-groups');
        }

        // Validation des données centralisée
        $error = $this->swimmerGroupDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/swimmers-groups');
        }

        $group->setName($_POST['name'] ?? '')
            ->setCoach(isset($_POST['coach']) && $_POST['coach'] !== '' ? mb_convert_case($_POST['coach'], MB_CASE_TITLE, "UTF-8") : null)
            ->setIsActive(isset($_POST['is_active']))
            ->setOrder((int)($_POST['order'] ?? 0));

        $this->swimmerGroupRepository->update($group);
        $this->flashMessageService->setFlashMessage('success', "Groupe modifié.");
        $this->redirect('/gestion/swimmers-groups');

    }

    #[Route('/gestion/swimmers-groups/delete', name: 'app_gestion_swimmers-groups_delete', methods: ['POST'])]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'swimmers-groups');

        $groupId = (int)($_POST['group_id'] ?? 0);
        //On vérifie s'il y a des nageurs dans ce groupe
        $swimmerRepository = new SwimmerRepository();
        $swimmers = $swimmerRepository->findByGroupId($groupId, true);

        if (count($swimmers) > 0) {
            $this->flashMessageService->setFlashMessage('danger', "Suppression impossible car le groupe n'est pas vide.");
            $this->redirect('/gestion/swimmers-groups');
        }

        $ok = $this->swimmerGroupRepository->delete($groupId);
        if (!$ok) {
            $error = $this->swimmerGroupRepository->getLastError();
            $this->flashMessageService->setFlashMessage('danger', "Erreur SQL : $error");
            $this->redirect('/gestion/swimmers-groups');
        }

        $this->flashMessageService->setFlashMessage('success', "Groupe supprimé.");
        $this->redirect('/gestion/swimmers-groups');
    }

}