<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Tarif\Tarif;
use app\Repository\Tarif\TarifRepository;
use app\Services\DataValidation\TarifDataValidationService;

class TarifController extends AbstractController
{
    private TarifRepository $tarifRepository;
    private TarifDataValidationService $tarifDataValidationService;

    public function __construct()
    {
        parent::__construct(false);
        $this->tarifRepository = new TarifRepository();
        $this->tarifDataValidationService = new TarifDataValidationService();

    }

    #[Route('/gestion/tarifs', name: 'app_gestion_tarifs')]
    public function index(): void
    {
        $onglet = $_GET['onglet'] ?? null;

        switch ($onglet) {
            case 'places':
                $tarifs = $this->tarifRepository->findBySeatType(true);
                break;
            case 'autres':
                $tarifs = $this->tarifRepository->findBySeatType(false);
                break;
            default:
                $tarifs = $this->tarifRepository->findAll();
                break;
        }

        $this->render('/gestion/tarifs', [
            'data' => $tarifs,
            'onglet' => $onglet
        ], 'Gestion des tarifs');

    }

    #[Route('/gestion/tarifs/add', name: 'app_gestion_tarifs_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'tarifs');

        $onglet = $_POST['onglet'] ?? null;
        $redirectUrl = '/gestion/tarifs' . ($onglet ? '?onglet=' . urlencode($onglet) : '');

        // Validation des données centralisée
        $error = $this->tarifDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect($redirectUrl);
        }

        $tarif = new Tarif();
        $tarif->setName($this->tarifDataValidationService->getName())
            ->setDescription($this->tarifDataValidationService->getDescription())
            ->setSeatCount($this->tarifDataValidationService->getSeatCount())
            ->setMinAge($this->tarifDataValidationService->getMinAge())
            ->setMaxAge($this->tarifDataValidationService->getMaxAge())
            ->setMaxTickets($this->tarifDataValidationService->getMaxTickets())
            ->setPrice($this->tarifDataValidationService->getPrice())
            ->setSeatCount($this->tarifDataValidationService->getSeatCount())
            ->setIncludesProgram($this->tarifDataValidationService->getIncludesProgram())
            ->setRequiresProof($this->tarifDataValidationService->getRequiresProof())
            ->setAccessCode($this->tarifDataValidationService->getAccessCode())
            ->setIsActive($this->tarifDataValidationService->getIsActive());

        $this->tarifRepository->insert($tarif);
        $this->flashMessageService->setFlashMessage('success', "Tarif ajouté.");
        $this->redirect($redirectUrl);
    }

    #[Route('/gestion/tarifs/update', name: 'app_gestion_tarifs_update', methods: ['POST'])]
    public function update(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'tarifs');

        $onglet = $_POST['onglet'] ?? null;
        $redirectUrl = '/gestion/tarifs' . ($onglet ? '?onglet=' . urlencode($onglet) : '');

        $tarifId = (int)($_POST['tarif_id'] ?? 0);
        $tarif = $this->tarifRepository->findById($tarifId);

        if (!$tarif) {
            $this->flashMessageService->setFlashMessage('danger', "Tarif non trouvé.");
            $this->redirect($redirectUrl);
        }

        // Validation des données centralisée
        $error = $this->tarifDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect($redirectUrl);
        }

        $tarif->setName($this->tarifDataValidationService->getName())
            ->setDescription($this->tarifDataValidationService->getDescription())
            ->setSeatCount($this->tarifDataValidationService->getSeatCount())
            ->setMinAge($this->tarifDataValidationService->getMinAge())
            ->setMaxAge($this->tarifDataValidationService->getMaxAge())
            ->setMaxTickets($this->tarifDataValidationService->getMaxTickets())
            ->setPrice($this->tarifDataValidationService->getPrice())
            ->setIncludesProgram($this->tarifDataValidationService->getIncludesProgram())
            ->setRequiresProof($this->tarifDataValidationService->getRequiresProof())
            ->setAccessCode($this->tarifDataValidationService->getAccessCode())
            ->setIsActive($this->tarifDataValidationService->getIsActive());

        $this->tarifRepository->update($tarif);
        $this->flashMessageService->setFlashMessage('success', "Tarif modifié.");
        $this->redirect($redirectUrl);
    }

    #[Route('/gestion/tarifs/delete', name: 'app_gestion_tarifs_delete', methods: ['POST'])]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedThis(2, 'tarifs');

        $onglet = $_POST['onglet'] ?? null;
        $redirectUrl = '/gestion/tarifs' . ($onglet ? '?onglet=' . urlencode($onglet) : '');

        $tarifId = (int)($_POST['tarif_id'] ?? 0);
        $tarif = $this->tarifRepository->findById($tarifId);

        if (!$tarif) {
            $this->flashMessageService->setFlashMessage('danger', "Tarif non trouvé.");
            $this->redirect($redirectUrl);
        }

        //On vérifie si le tarif est utilisé
        if ($this->tarifRepository->isUsed($tarifId)) {
            $this->flashMessageService->setFlashMessage('danger', "Impossible de supprimer ce tarif.\nIl est utilisé dans au moins un événement.");
        }

        $this->tarifRepository->delete($tarifId);
        $this->flashMessageService->setFlashMessage('success', "Tarif supprimé.");
        $this->redirect($redirectUrl);

    }



}