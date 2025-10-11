<?php
namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\Event\EventPresentationsRepository;
use app\Services\Event\EventPresentationService;

class HomeController extends AbstractController
{
    private EventPresentationsRepository $eventPresentationsRepository;
    private EventPresentationService $eventPresentationService;

    public function __construct(
        EventPresentationsRepository $eventPresentationsRepository,
        EventPresentationService $eventPresentationService,
    )
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
        $this->eventPresentationsRepository = $eventPresentationsRepository;
        $this->eventPresentationService = $eventPresentationService;
    }

    #[Route('/', name: 'app_home')]
    public function index(): void
    {
        $eventPresentations = $this->eventPresentationsRepository->findAll(true, true);

        $contents = $this->eventPresentationService->addLinkToAllPictures($eventPresentations);

        $this->render('home', [
            'contents' => $contents
        ], 'Accueil');
    }
}
