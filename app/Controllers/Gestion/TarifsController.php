<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Tarifs;
use app\Repository\TarifsRepository;
use app\Services\FlashMessageService;
use DateMalformedStringException;

#[Route('/gestion/tarifs', name: 'app_gestion_tarifs')]
class TarifsController extends AbstractController
{
    private TarifsRepository $repository;
    private FlashMessageService $flashMessageService;

    public function __construct()
    {
        parent::__construct(false);
        $this->repository = new TarifsRepository();
        $this->flashMessageService = new FlashMessageService();
    }

    public function index(): void
    {
        $onglet = $_GET['onglet'] ?? ($_SESSION['onglet_tarif'] ?? 'all');
        $_SESSION['onglet_tarif'] = $onglet;
        $tarifs = $this->repository->findAll($onglet);

        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/tarifs', [
            'data' => $tarifs,
            'flash_message' => $flashMessage,
            'onglet' => $onglet
        ], 'Gestion des tarifs');
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/gestion/tarifs/add', name: 'app_gestion_tarifs_add')]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tarif = new Tarifs();
            $tarif->setId((int)($_POST['id'] ?? 0))
                ->setLibelle($_POST['libelle'] ?? '')
                ->setDescription($_POST['description'] ?? null)
                ->setNbPlace(isset($_POST['nb_place']) && $_POST['nb_place'] !== '' ? (int)$_POST['nb_place'] : null)
                ->setAgeMin(isset($_POST['age_min']) && $_POST['age_min'] !== '' ? (int)$_POST['age_min'] : null)
                ->setAgeMax(isset($_POST['age_max']) && $_POST['age_max'] !== '' ? (int)$_POST['age_max'] : null)
                ->setMaxTickets(isset($_POST['max_tickets']) && $_POST['max_tickets'] !== '' ? (int)$_POST['max_tickets'] : null)
                ->setPrice((int)((float)str_replace(',', '.', $_POST['price'] ?? '0') * 100))
                ->setIsProgramShowInclude(isset($_POST['is_program_show_include']))
                ->setIsProofRequired(isset($_POST['is_proof_required']))
                ->setAccessCode($_POST['access_code'] ?? null)
                ->setIsActive(isset($_POST['is_active']))
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->repository->insert($tarif);
            $_SESSION['onglet_tarif'] = $tarif->getNbPlace() !== null ? 'places' : 'autres';
            $this->flashMessageService->setFlashMessage('success', "Tarif ajouté.");
            header('Location: /gestion/tarifs');
            exit;
        }
    }

    /**
     * @throws DateMalformedStringException
     */
    #[Route('/gestion/tarifs/update/{id}', name: 'app_gestion_tarifs_update')]
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tarif = $this->repository->findById($id);
            if ($tarif) {
                $tarif->setLibelle($_POST['libelle'] ?? '')
                    ->setDescription($_POST['description'] ?? null)
                    ->setNbPlace(isset($_POST['nb_place']) && $_POST['nb_place'] !== '' ? (int)$_POST['nb_place'] : null)
                    ->setAgeMin(isset($_POST['age_min']) && $_POST['age_min'] !== '' ? (int)$_POST['age_min'] : null)
                    ->setAgeMax(isset($_POST['age_max']) && $_POST['age_max'] !== '' ? (int)$_POST['age_max'] : null)
                    ->setMaxTickets(isset($_POST['max_tickets']) && $_POST['max_tickets'] !== '' ? (int)$_POST['max_tickets'] : null)
                    ->setPrice((int)((float)str_replace(',', '.', $_POST['price'] ?? '0') * 100))
                    ->setIsProgramShowInclude(isset($_POST['is_program_show_include']))
                    ->setIsProofRequired(isset($_POST['is_proof_required']))
                    ->setAccessCode($_POST['access_code'] ?? null)
                    ->setIsActive(isset($_POST['is_active']));
                $this->repository->update($tarif);
                $_SESSION['onglet_tarif'] = $tarif->getNbPlace() !== null ? 'places' : 'autres';
                $this->flashMessageService->setFlashMessage('success', "Tarif modifié.");
            }
            header('Location: /gestion/tarifs');
            exit;
        }
    }

    #[Route('/gestion/tarifs/delete/{id}', name: 'app_gestion_tarifs_delete')]
    public function delete($id)
    {
        $this->repository->delete((int)$id);
        $this->flashMessageService->setFlashMessage('success', "Tarif supprimé.");
        header('Location: /gestion/tarifs');
        exit;
    }
}