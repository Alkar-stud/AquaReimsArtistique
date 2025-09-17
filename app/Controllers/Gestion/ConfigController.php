<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\ConfigRepository;
use app\Services\FlashMessageService;

#[Route('/gestion/configuration/configs', name: 'app_gestion_configs')]
class ConfigController extends AbstractController
{
    private ConfigRepository $repository;
    private FlashMessageService $flashMessageService;

    public function __construct()
    {
        parent::__construct(false);
        $this->repository = new ConfigRepository();
        $this->flashMessageService = new FlashMessageService();
    }

    public function index(): void
    {
        $configs = $this->repository->findAll();

        $flashMessage = $this->flashMessageService->getFlashMessage();
        $this->flashMessageService->unsetFlashMessage();

        $this->render('/gestion/configs', [
            'data' => $configs,
            'flash_message' => $flashMessage
        ], 'Gestion des configurations');
    }

    #[Route('/gestion/configuration/configs/add', name: 'app_gestion_configs_add')]
    public function add(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $config = new \app\Models\Config();
            $config->setLibelle($_POST['libelle'] ?? '')
                ->setConfigKey($_POST['config_key'] ?? '')
                ->setConfigValue($_POST['config_value'] ?? '')
                ->setConfigType($_POST['config_type'] ?? null)
                ->setCreatedAt(date('Y-m-d H:i:s'));
            $this->repository->insert($config);
            $this->flashMessageService->setFlashMessage('success', "Configuration ajoutée.");
            header('Location: /gestion/configuration/configs');
            exit;
        }
    }

    #[Route('/gestion/configuration/configs/update/{id}', name: 'app_gestion_configs_update')]
    public function update(int $id): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $config = $this->repository->findById($id);
            if ($config) {
                $config->setLibelle($_POST['libelle'] ?? $config->getLibelle())
                    ->setConfigKey($_POST['config_key'] ?? $config->getConfigKey())
                    ->setConfigValue($_POST['config_value'] ?? $config->getConfigValue())
                    ->setConfigType($_POST['config_type'] ?? $config->getConfigType());
                $this->repository->update($config);
                $this->flashMessageService->setFlashMessage('success', "Configuration modifiée.");
            }
            header('Location: /gestion/configuration/configs');
            exit;
        }
    }

    #[Route('/gestion/configuration/configs/delete/{id}', name: 'app_gestion_configs_delete')]
    public function delete(int $id): void
    {
        $this->repository->delete($id);
        $this->flashMessageService->setFlashMessage('success', "Configuration supprimée.");
        header('Location: /gestion/configuration/configs');
        exit;
    }

}