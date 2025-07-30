<?php

namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\TarifsRepository;
use app\Models\Tarifs;

#[Route('/gestion/tarifs', name: 'app_gestion_tarifs')]
class TarifsController extends AbstractController
{
    private TarifsRepository $repository;

    public function __construct()
    {
        parent::__construct(false);
        $this->repository = new TarifsRepository();
    }

    public function index(): void
    {
        $onglet = $_GET['onglet'] ?? ($_SESSION['onglet_tarif'] ?? 'all');
        $_SESSION['onglet_tarif'] = $onglet;
        $tarifs = $this->repository->findAll($onglet);
        $this->render('/gestion/tarifs', $tarifs, 'Gestion des tarifs');
    }

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
                ->setPrice((float)($_POST['price'] ?? 0))
                ->setIsProgramShowInclude(isset($_POST['is_program_show_include']))
                ->setIsProofRequired(isset($_POST['is_proof_required']))
                ->setAccessCode($_POST['access_code'] ?? null)
                ->setIsActive(isset($_POST['is_active']))
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->repository->insert($tarif);
            $_SESSION['onglet_tarif'] = $tarif->getNbPlace() !== null ? 'places' : 'autres';
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Tarif ajouté'];
            header('Location: /gestion/tarifs');
            exit;
        }
    }

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
                    ->setPrice((float)($_POST['price'] ?? 0))
                    ->setIsProgramShowInclude(isset($_POST['is_program_show_include']))
                    ->setIsProofRequired(isset($_POST['is_proof_required']))
                    ->setAccessCode($_POST['access_code'] ?? null)
                    ->setIsActive(isset($_POST['is_active']));
                $this->repository->update($tarif);
                $_SESSION['onglet_tarif'] = $tarif->getNbPlace() !== null ? 'places' : 'autres';
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Tarif modifié'];
            }
            header('Location: /gestion/tarifs');
            exit;
        }
    }

    #[Route('/gestion/tarifs/delete/{id}', name: 'app_gestion_tarifs_delete')]
    public function delete($id)
    {
        $this->repository->delete((int)$id);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Tarif supprimé'];
        header('Location: /gestion/tarifs');
        exit;
    }
}