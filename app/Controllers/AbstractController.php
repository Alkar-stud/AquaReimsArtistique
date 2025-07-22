<?php

namespace app\Controllers;

abstract class AbstractController
{
    // ...
    protected function render(string $view, array $data = [], string $title = ''): void
    {
        extract($data);
        ob_start();

        $page = __DIR__ . '/../views/' . $view . '.html.php';

        // On vérifie que le fichier de vue existe avant de l'inclure
        if (!file_exists($page)) {
            ob_end_clean(); // Nettoie le buffer en cas d'erreur
            // Affiche une erreur claire. En production, on afficherait une page 404.
            trigger_error("La vue '$page' n'existe pas.", E_USER_ERROR);
            return;
        }

        include $page;
        $content = ob_get_clean();
        require __DIR__ . '/../views/base.html.php';
    }
}