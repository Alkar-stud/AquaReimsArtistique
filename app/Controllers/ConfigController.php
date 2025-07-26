<?php

namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\ConfigRepository;

#[Route('/gestion/configuration/configs', name: 'app_gestion_configs')]
class ConfigController extends AbstractController
{
    private ConfigRepository $repository;

    public function __construct()
    {
        parent::__construct(false);
        $this->repository = new ConfigRepository();
    }

    public function index(): void
    {
        $configs = $this->repository->findAll();
        $this->render('/gestion/configs', $configs, 'Gestion des configurations');
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
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Configuration ajoutée'];
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
                $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Configuration modifiée'];
            }
            header('Location: /gestion/configuration/configs');
            exit;
        }
    }

    #[Route('/gestion/configuration/configs/delete/{id}', name: 'app_gestion_configs_delete')]
    public function delete(int $id): void
    {
        $this->repository->delete($id);
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'Configuration supprimée'];
        header('Location: /gestion/configuration/configs');
        exit;
    }

}