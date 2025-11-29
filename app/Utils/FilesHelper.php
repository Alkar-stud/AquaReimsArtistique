<?php

namespace app\Utils;

use app\Services\Log\Logger;

class FilesHelper
{

    /**
     *  La fonction force le téléchargement d'un fichier.
     *
     * @param string $name : nom du fichier
     * @param string $filePath : emplacement sur le serveur web
     * @param int $weight : poids du fichier en octets
     * @return void : void
     */
    public function forcerTelechargement(string $name, string $filePath, int $weight)
    {
        header('Content-Type: application/octet-stream');
        header('Content-Length: '. $weight);
        header('Content-disposition: attachment; filename='. $name);
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        header('Expires: 0');
        readfile($filePath);
        exit();
    }

    /**
     * Supprime un fichier dans un dossier spécifié.
     *
     * @param string $directoryPath Le chemin du dossier (depuis la racine du projet, ex: 'public/uploads/').
     * @param string $fileName Le nom du fichier à supprimer.
     * @return bool True si la suppression a réussi ou si le fichier n'existait pas, false en cas d'échec.
     */
    public function deleteFile(string $directoryPath, string $fileName): bool
    {
        if (empty($fileName)) {
            return true; // Rien à faire, succès.
        }

        // Construire le chemin complet et le normaliser
        $fullPath = realpath(__DIR__ . '/../../' . $directoryPath) . DIRECTORY_SEPARATOR . $fileName;

        // Sécurité : s'assurer que le chemin résolu est bien dans le dossier attendu pour éviter les attaques par traversée de répertoire.
        $baseDir = realpath(__DIR__ . '/../../' . $directoryPath);
        if (!$baseDir || !str_starts_with($fullPath, $baseDir)) {
            error_log("Tentative de suppression de fichier non autorisée : " . $fullPath);
            return false;
        }

        if (file_exists($fullPath) && is_file($fullPath)) {
            $success = unlink($fullPath);
            if (!$success) {
                $error = error_get_last();
                Logger::get()->error(
                    'FILESYSTEM',
                    'Échec de la suppression du fichier.',
                    [
                        'path' => $fullPath,
                        'error' => $error['message'] ?? 'Erreur inconnue lors de la suppression.'
                    ]
                );
                return false;
            }
        }

        return true; // Le fichier n'existait pas, on considère que c'est un succès.
    }
}