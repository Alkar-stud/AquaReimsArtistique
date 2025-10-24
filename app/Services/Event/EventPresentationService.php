<?php

namespace app\Services\Event;

use DOMDocument;
use Exception;

class EventPresentationService
{

    /**
     * Pour ajouter des liens sur les images de présentation
     * @param array $contents
     * @return array
     */
    public function addLinkToAllPictures(array $contents): array
    {
        // On traite chaque contenu pour transformer les images en liens
        foreach ($contents as $content) {
            try {
                $html = $content->getContent();
                if (empty(trim($html))) {
                    continue;
                }
                // On indique à libxml de gérer les erreurs en interne pour éviter les warnings sur les balises HTML5
                $previousLibXmlErrors = libxml_use_internal_errors(true);

                $doc = new DOMDocument();
                // On charge le HTML en supprimant les erreurs de parsing et sans ajouter de balises <html> ou <body>
                $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                libxml_use_internal_errors($previousLibXmlErrors);

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
                            $figcaption = $doc->createElement('figcaption', 'Vous pouvez cliquer sur l\'image pour réserver pour : ' . htmlspecialchars($content->getEventObject()->getName()));
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
                        $image->setAttribute('alt', 'Réserver pour ' . htmlspecialchars($content->getEventObject()->getName()));
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

        return $contents;

    }

}