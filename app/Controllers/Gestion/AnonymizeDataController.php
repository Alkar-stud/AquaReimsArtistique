<?php

namespace app\Controllers\Gestion;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Services\Anonymize\AnonymizeDataService;
use Exception;

class AnonymizeDataController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(false);
    }

}
