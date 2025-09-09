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
        $accueilRepository = new AccueilRepository();
        $contents = $accueilRepository->findDisplayed();

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

                // On supprime le style en ligne 'width' ajouté par CKEditor sur les figures pour permettre le responsive
                $figures = $doc->getElementsByTagName('figure');
                foreach ($figures as $figure) {
                    if (str_contains($figure->getAttribute('class'), 'image_resized')) {
                        // On supprime le style en ligne qui fixe la largeur
                        $figure->removeAttribute('style');
                        // On ajoute des classes Bootstrap pour un affichage responsive
                        $currentClass = $figure->getAttribute('class');
                        $figure->setAttribute('class', $currentClass . ' mx-auto');

                        // Ajout de la figcaption si un événement est associé
                        if ($content->getEventObject()) {
                            $figcaption = $doc->createElement('figcaption', 'Vous pouvez cliquer sur l\'image pour réserver pour : ' . htmlspecialchars($content->getEventObject()->getLibelle()));
                             $figure->appendChild($figcaption);
                         }
                     }
                }
                $images = $doc->getElementsByTagName('img');
                foreach ($images as $image) {
                    $link = $doc->createElement('a');
                    $link->setAttribute('href', '/reservation');

                    // Ajout du alt à l'image
                    if ($content->getEventObject()) {
                        $image->setAttribute('alt', 'Réserver pour ' . htmlspecialchars($content->getEventObject()->getLibelle()));
                    }

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
