<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Swimmer\SwimmerGroup;
use app\Repository\Swimmer\SwimmerGroupRepository;

class SwimmersGroupsController extends AbstractController
{
    private SwimmerGroupRepository $swimmerGroupRepository;

    public function __construct()
    {
        parent::__construct(false);
        $this->swimmerGroupRepository = new SwimmerGroupRepository();
    }

    #[Route('/gestion/swimmers-groups', name: 'app_gestion_swimmers_groups')]
    public function index(): void
    {
        $swimmersGroups = $this->swimmerGroupRepository->findAll();

        $this->render('/gestion/swimmers_groups', [
                'data' => $swimmersGroups,
                'currentUser' => $this->currentUser,
                'csrf_token_add' => $this->csrfService->getToken('/gestion/swimmers_groups/add'),
                'csrf_token_edit' => $this->csrfService->getToken('/gestion/swimmers_groups/update'),
                'csrf_token_delete' => $this->csrfService->getToken('/gestion/swimmers_groups/delete')
            ], 'Gestion des groupes de nageurs');
    }

    #[Route('/gestion/swimmers_groups/add', name: 'app_gestion_swimmers_groups_add', methods: ['POST'])]
    public function add(): void
    {
        $groupe = new SwimmerGroup();
        $groupe->setName($_POST['name'] ?? '')
            ->setCoach(isset($_POST['coach']) && $_POST['coach'] !== '' ? mb_convert_case($_POST['coach'], MB_CASE_TITLE, "UTF-8") : null)
            ->setIsActive(isset($_POST['is_active']))
            ->setOrder((int)($_POST['order'] ?? 0));
        $this->swimmerGroupRepository->insert($groupe);
        $this->flashMessageService->setFlashMessage('success', "Groupe ajouté");
        $this->redirect('/gestion/swimmers-groups');

    }

}