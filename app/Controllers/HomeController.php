<?php
namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\Event\EventPresentationsRepository;
use DOMDocument;
use Exception;

#[Route('/', name: 'app_home')]

class HomeController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }

    /**
     * @throws Exception
     */
    public function index(): void
    {

        $this->render('home', [], 'Accueil');
    }
}
