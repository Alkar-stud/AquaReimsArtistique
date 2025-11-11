<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Piscine\Piscine;
use app\Repository\Piscine\PiscineRepository;
use app\Services\DataValidation\PiscineDataValidationService;

class PiscineController extends AbstractController
{
    private PiscineRepository $piscineRepository;
    private PiscineDataValidationService $piscineDataValidationService;

    public function __construct()
    {
        parent::__construct(false); // true = route publique
        $this->piscineRepository = new PiscineRepository();
        $this->piscineDataValidationService = new PiscineDataValidationService();
    }

    #[Route('/gestion/piscines', name: 'app_gestion_piscines')]
    public function index(): void
    {
        $piscines = $this->piscineRepository->findAll();

        $this->render('/gestion/piscines', [
            'data' => $piscines,
            'currentUser' => $this->currentUser
        ], 'Gestion des piscines');
    }


    #[Route('/gestion/piscines/add', name: 'app_gestion_piscines_add', methods: ['POST'])]
    public function add()
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'piscines');

        // Validation des données centralisée
        $error = $this->piscineDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/piscines');
        }

        $piscine = new Piscine();
        $piscine->setLabel($this->piscineDataValidationService->getLabel())
            ->setAddress($this->piscineDataValidationService->getAddress())
            ->setMaxPlaces($this->piscineDataValidationService->getMaxPlaces())
            ->setNumberedSeats($this->piscineDataValidationService->getNumberedSeats());

        $this->piscineRepository->insert($piscine);
        $this->flashMessageService->setFlashMessage('success', "Piscine ajoutée.");
        $this->redirect('/gestion/piscines');
    }


    #[Route('/gestion/piscines/update', name: 'app_gestion_piscines_update', methods: ['POST'])]
    public function update(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'piscines');
        $piscineId = (int)($_POST['piscine_id'] ?? 0);
        $piscine = $this->piscineRepository->findById($piscineId);

        if (!$piscine) {
            $this->flashMessageService->setFlashMessage('danger', "Piscine non trouvée.");
            $this->redirect('/gestion/piscines');
        }

        // Validation des données centralisée
        $error = $this->piscineDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/piscines');
        }

        $piscine->setLabel($this->piscineDataValidationService->getLabel())
            ->setAddress($this->piscineDataValidationService->getAddress())
            ->setMaxPlaces($this->piscineDataValidationService->getMaxPlaces())
            ->setNumberedSeats($this->piscineDataValidationService->getNumberedSeats());

        $this->piscineRepository->update($piscine);
        $this->flashMessageService->setFlashMessage('success', "Piscine modifiée.");
        $this->redirect('/gestion/piscines');
    }

    #[Route('/gestion/piscines/delete', name: 'app_gestion_piscines_delete', methods: ['POST'])]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'piscines');

        $piscineId = (int)($_POST['piscine_id'] ?? 0);
        $piscine = $this->piscineRepository->findById($piscineId);

        if (!$piscine) {
            $this->flashMessageService->setFlashMessage('danger', "Piscine non trouvée.");
            $this->redirect('/gestion/piscines');
        }

        $this->piscineRepository->delete($piscineId);
        $this->flashMessageService->setFlashMessage('success', "Piscine supprimée.");
        $this->redirect('/gestion/piscines');
    }

}