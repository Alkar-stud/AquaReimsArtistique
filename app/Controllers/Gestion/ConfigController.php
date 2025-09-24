<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Models\Config;
use app\Repository\ConfigRepository;
use app\Services\DataValidation\ConfigDataValidationService;

class ConfigController extends AbstractController
{
    private ConfigRepository $configRepository;
    private ConfigDataValidationService $configDataValidationService;

    public function __construct()
    {
        parent::__construct(false);
        $this->configRepository = new ConfigRepository();
        $this->configDataValidationService = new ConfigDataValidationService();
    }

    #[Route('/gestion/configs', name: 'app_gestion_configs')]
    public function index(): void
    {
        $configs = $this->configRepository->findAll();

        $this->render('/gestion/configs', [
            'data' => $configs,
            'currentUser' => $this->currentUser,
            'csrf_token_add' => $this->csrfService->getToken('/gestion/configs/add'),
            'csrf_token_edit' => $this->csrfService->getToken('/gestion/configs/update'),
            'csrf_token_delete' => $this->csrfService->getToken('/gestion/configs/delete')
        ], 'Gestion des configurations');
    }

    #[Route('/gestion/configs/add', name: 'app_gestion_configs_add', methods: ['POST'])]
    public function add(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedOthersUsers();

        // Validation des données centralisée
        $error = $this->configDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/configs');
        }

        $config = new Config();
        $config->setLabel($this->configDataValidationService->getLabel() ?? '')
            ->setConfigKey($this->configDataValidationService->getConfigKey() ?? '')
            ->setConfigValue($this->configDataValidationService->getConfigValue() ?? '')
            ->setConfigType($this->configDataValidationService->getConfigType() ?? null);
        $configId = $this->configRepository->insert($config);
        $this->flashMessageService->setFlashMessage('success', "Configuration ajoutée.");
        //on récupère le contexte
        $context = htmlspecialchars($_POST['context']) ?? 'desktop';
        $this->redirectWithAnchor('/gestion/configs', 'form_anchor', $configId, $context);
    }

    #[Route('/gestion/configs/update', name: 'app_gestion_configs_update', methods: ['POST'])]
    public function update(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedOthersUsers();

        //On récupère la config.
        $configId = (int)($_POST['config_id'] ?? 0);
        $config = $this->configRepository->findById($configId);
        if (!$config) {
            $this->flashMessageService->setFlashMessage('danger', "Configuration non trouvée.");
            $this->redirectWithAnchor('/gestion/configs');
        }

        // Validation des données centralisée
        $error = $this->configDataValidationService->checkData($_POST);
        if ($error) {
            $this->flashMessageService->setFlashMessage('danger', $error);
            $this->redirect('/gestion/configs');
        }

        if ($config) {
            $config->setLabel($this->configDataValidationService->getLabel() ?? $config->getLabel())
                ->setConfigKey($this->configDataValidationService->getConfigKey() ?? $config->getConfigKey())
                ->setConfigValue($this->configDataValidationService->getConfigValue() ?? $config->getConfigValue())
                ->setConfigType($this->configDataValidationService->getConfigType() ?? $config->getConfigType());
            $this->configRepository->update($config);
            $this->flashMessageService->setFlashMessage('success', "Configuration modifiée.");
        }
        $this->redirectWithAnchor('/gestion/configs');

    }

    #[Route('/gestion/configs/delete', name: 'app_gestion_configs_delete', methods: ['POST'])]
    public function delete(): void
    {
        //On vérifie que le CurrentUser a bien le droit de faire ça
        $this->checkIfCurrentUserIsAllowedToManagedOthersUsers();

        $configId = (int)($_POST['config_id'] ?? 0);
        $this->configRepository->delete($configId);
        $this->flashMessageService->setFlashMessage('success', "Configuration supprimée.");
        $this->redirect('/gestion/configs');
    }

}