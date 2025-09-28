<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Swimmer\Swimmer;
use app\Repository\Swimmer\SwimmerGroupRepository;
use app\Repository\Swimmer\SwimmerRepository;
use app\Services\DataValidation\SwimmerDataValidationService;

class SwimmerController extends AbstractController
{
    private SwimmerGroupRepository $swimmerGroupRepository;
    private SwimmerRepository $swimmerRepository;
    private SwimmerDataValidationService $swimmerDataValidationService;

    public function __construct()
    {
        parent::__construct(false);
        $this->swimmerGroupRepository = new SwimmerGroupRepository();
        $this->swimmerRepository = new SwimmerRepository();
        $this->swimmerDataValidationService = new SwimmerDataValidationService();
    }

    #[Route('/gestion/swimmers/{groupId}', name: 'app_gestion_swimmers_group', requirements: ['groupId' => 'all|0|\d+'])]
    public function index(mixed $groupId = 'all'): void
    {
        $swimmersGroups = $this->swimmerGroupRepository->findAll();

        if ($groupId === 'all') {
            $groupName = 'tous les groupes';
            $swimmers = $this->swimmerRepository->findAll(true);
        } elseif ($groupId == 0) {
            $this->flashMessageService->setFlashMessage('warning', 'Vous n\'avez ici que les nageurs sans groupe');
            $groupName = 'sans groupe';
            $swimmers = $this->swimmerRepository->findWithoutGroup();
        } elseif (!is_numeric($groupId)) {
            $this->flashMessageService->setFlashMessage('danger', 'Ce groupe n\'existe pas');
            $this->render('/gestion/swimmers', [
                'swimmers' => [],
                'groupes' => $swimmersGroups,
                'groupId' => $groupId,
                'GroupName' => '',
                'flash_message' => $this->flashMessageService->getFlashMessage(),
                'currentUser' => $this->currentUser
            ], 'Gestion des nageurs');
            return;
        } else {
            $swimmersInGroup = $this->swimmerGroupRepository->findById((int)$groupId, true);
            $groupName = $swimmersInGroup->getName();
            $swimmers = $swimmersInGroup->getSwimmers();
        }

        $this->render('/gestion/swimmers', [
            'swimmers' => $swimmers,
            'groupes' => $swimmersGroups,
            'groupId' => $groupId,
            'GroupName' => $groupName,
            'flash_message' => $this->flashMessageService->getFlashMessage(),
            'currentUser' => $this->currentUser
        ], 'Gestion du groupe  ' . $groupName);
    }


    #[Route('/gestion/swimmers/add', name: 'app_gestion_swimmers_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'swimmers');

        // Validation des données centralisée
        $error = $this->swimmerDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/swimmers');
        }

        $swimmer = new Swimmer();
        $swimmer->setName($this->swimmerDataValidationService->getName())
            ->setGroup($this->swimmerDataValidationService->getGroup());
        $this->swimmerRepository->insert($swimmer);
        $this->flashMessageService->setFlashMessage('success', "Nageu.r.se ajouté.e");
        $this->redirect('/gestion/swimmers/' . $swimmer->getGroup());
    }

    #[Route('/gestion/swimmers/update', name: 'app_gestion_swimmers_update', methods: ['POST'])]
    public function update(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'swimmers-groups');

        //On récupère le nageur et le groupe d'origine
        $swimmerId = (int)($_POST['swimmer_id'] ?? 0);
        $swimmer = $this->swimmerRepository->findById($swimmerId);

        if (!$swimmer) {
            $this->flashMessageService->setFlashMessage('danger', "Nageu.r.se non trouvé.e.");
            $this->redirect('/gestion/swimmers');
        }

        // Validation des données centralisée
        $error = $this->swimmerDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/swimmers');
        }

        $originGroupId = $swimmer->getGroup();

        $swimmer->setName($this->swimmerDataValidationService->getName())
            ->setGroup($this->swimmerDataValidationService->getGroup());

        $this->swimmerRepository->update($swimmer);
        $this->flashMessageService->setFlashMessage('success', "Nageu.r.se modifié.e et/ou déplacé.e.");
        $originGroupId !== $swimmer->getGroup() ? $group = $originGroupId: $group = $swimmer->getGroup();
        $context = htmlspecialchars($_POST['context']) ?? 'desktop';

        $this->redirectWithAnchor('/gestion/swimmers/' . $group, 'form_anchor', $swimmer->getId(), $context);
    }


    #[Route('/gestion/swimmers/delete', name: 'app_gestion_swimmers_delete', methods: ['POST'])]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'swimmers-groups');

        //On récupère le nageur et le groupe d'origine
        $swimmerId = (int)($_POST['swimmer_id'] ?? 0);
        $swimmer = $this->swimmerRepository->findById($swimmerId);

        if (!$swimmer) {
            $this->flashMessageService->setFlashMessage('danger', "Nageu.r.se non trouvé.e.");
            $this->redirect('/gestion/swimmers');
        }

        $this->swimmerRepository->delete($swimmerId);

        $this->flashMessageService->setFlashMessage('success', "Nageu.r.se supprimé.e.");
        $this->redirect('/gestion/swimmers/' . $swimmer->getGroup() );
    }

}