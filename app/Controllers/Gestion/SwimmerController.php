<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Swimmer\Swimmer;
use app\Repository\Swimmer\SwimmerGroupRepository;
use app\Repository\Swimmer\SwimmerRepository;

class SwimmerController extends AbstractController
{
    private SwimmerGroupRepository $swimmerGroupRepository;
    private SwimmerRepository $swimmerRepository;

    public function __construct()
    {
        parent::__construct(false);
        $this->swimmerGroupRepository = new SwimmerGroupRepository();
        $this->swimmerRepository = new SwimmerRepository();
    }

    #[Route('/gestion/swimmers/{groupId}', name: 'app_gestion_swimmers_group')]
    public function index(int $groupId = 0): void
    {
        //On récupère tous les groupes
        $swimmersGroups = $this->swimmerGroupRepository->findAll();

        if ($groupId == 0) {


        }



        $this->render('/gestion/swimmers', [
            'data' => $swimmersGroups,
            'currentUser' => $this->currentUser,
            'csrf_token_add' => $this->csrfService->getToken('/gestion/swimmers/add'),
            'csrf_token_edit' => $this->csrfService->getToken('/gestion/swimmers/update'),
            'csrf_token_delete' => $this->csrfService->getToken('/gestion/swimmers/delete')
        ], 'Gestion des ' . $titre);
    }


    #[Route('/gestion/swimmers/add', name: 'app_gestion_swimmers_add', methods: ['POST'])]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nageuse = new Swimmer();
            $nageuse->setName(mb_convert_case($_POST['name'], MB_CASE_TITLE, "UTF-8") ?? '')
                ->setGroupe(isset($_POST['groupe']) ? (int)$_POST['groupe'] : null)
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->swimmerRepository->insert($nageuse);
            $this->flashMessageService->setFlashMessage('success', "Nageuse ajoutée");
            header('Location: /gestion/nageuses/' . $nageuse->getGroupe());
            exit;
        } else {
            $this->flashMessageService->setFlashMessage('danger', "Erreur lors de l'ajout");
        }
    }
}