<?php
namespace app\Controllers;

use app\Attributes\Route;
use app\Repository\AccueilRepository;
use DOMDocument;
use Exception;

#[Route('/', name: 'app_home')]

class HomeController extends AbstractController
{
    public function __construct()
    {
        parent::__construct(true); // true = route publique, pas de vérif session pour éviter le TOO_MANY_REDIRECT
    }
    public function index(): void
    {
        $repository = new AccueilRepository();
        $contents = $repository->findDisplayed();

        // On traite chaque contenu pour transformer les images en liens
        foreach ($contents as $content) {
            try {
                $html = $content->getContent();
                if (empty(trim($html))) {
                    continue;
                }

                $doc = new DOMDocument();
                // On charge le HTML en supprimant les erreurs de parsing et sans ajouter de balises <html> ou <body>
                @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                $images = $doc->getElementsByTagName('img');
                foreach ($images as $image) {
                    $link = $doc->createElement('a');
                    $link->setAttribute('href', '/reservation');

                    // On remplace l'image par le lien, et on met l'image à l'intérieur du lien
                    $image->parentNode->replaceChild($link, $image);
                    $link->appendChild($image);
                }
                $content->setContent($doc->saveHTML());
            } catch (Exception $e) {
                error_log("Erreur lors du parsing HTML pour le contenu d'accueil ID " . $content->getId() . ": " . $e->getMessage());
            }
        }

        $this->render('home', ['contents' => $contents], 'Accueil');
    }
}
