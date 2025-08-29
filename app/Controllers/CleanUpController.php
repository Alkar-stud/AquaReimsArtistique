<?php
namespace app\Controllers;

use app\Attributes\Route;

#[Route('/cleanup', name: 'app_cleanup')]
class CleanUpController extends AbstractController
{
    private const CLEANUP_TOKEN = ACCESS_TOKEN;
    private const EXPIRE_AFTER_SECONDS = 3600; // 1h

    #[Route('/cleanup/temp-proofs', methods: ['GET'])]
    public function cleanTempProofs(): void
    {
        $token = $_GET['token'] ?? '';
        if ($token !== self::CLEANUP_TOKEN) {
            http_response_code(403);
            echo 'Accès refusé';
            exit;
        }

        $dir = __DIR__ . '/../../..' . UPLOAD_PROOF_PATH . 'temp/';
        $expireAfter = self::EXPIRE_AFTER_SECONDS;
        $deleted = 0;
        $errors = [];

        foreach (glob($dir . '*') as $file) {
            if (is_file($file) && (time() - filemtime($file)) > $expireAfter) {
                if (@unlink($file)) {
                    $deleted++;
                } else {
                    $errors[] = basename($file);
                }
            }
        }

        $this->json([
            'success' => true,
            'deleted' => $deleted,
            'errors' => $errors
        ]);
    }
}