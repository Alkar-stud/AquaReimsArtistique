<?php
namespace app\Controllers;

use app\Enums\LogType;
use app\Services\Log\Logger;

class ErrorController extends AbstractController
{
    public function __construct()
    {
        // Page publique : pas de vérif de session/redirection
        parent::__construct(true);
    }

    public function notFound(): void
    {
        http_response_code(404);

        $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        $redirectUrl = str_starts_with($uri, '/gestion') ? '/gestion' : '/';

        // Log "url_error" pour les 404
        Logger::get()->notice(
            LogType::URL_ERROR->value,
            'not_found',
            [
                'uri' => $uri,
                'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            ]
        );


        $this->render('errors/404', [
            'uri' => $uri,
            'redirectUrl' => $redirectUrl,
            'is_gestion_page' => str_starts_with($uri, '/gestion'),
            'load_ckeditor' => false,
        ], '404');
    }
}
