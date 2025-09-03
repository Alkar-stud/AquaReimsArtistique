<?php

namespace app\Services;

use Exception;

class UploadService
{
    /**
     * Gère le téléversement d'un fichier image pour CKEditor (entre autre)
     *
     * @param array $fileData Le tableau de fichier provenant de $_FILES['upload'].
     * @param string|null $displayUntil La date de fin d'affichage pour nommer le fichier.
     * @return string L'URL publique du fichier téléversé.
     * @throws Exception Si une erreur survient.
     */
    public function handleImageUpload(array $fileData, ?string $displayUntil = null): string
    {
        // --- Vérifications de sécurité ---
        if ($fileData['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors du téléversement du fichier.');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($fileData['type'], $allowedMimeTypes)) {
            throw new Exception('Type de fichier non autorisé.');
        }

        if ($fileData['size'] > 2 * 1024 * 1024) { // Limite à 2 Mo
            throw new Exception('Le fichier est trop volumineux (max 2Mo).');
        }

        // --- Traitement du fichier ---
        $uploadDir = __DIR__ . '/../../public/images/accueil/';
        // S'assurer que le chemin est absolu
        $uploadDir = realpath($uploadDir) ?: $uploadDir;

        // Créer le dossier s'il n'existe pas
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0775, true)) {
                throw new Exception('Impossible de créer le dossier de destination.');
            }
        }

        // --- Génération du nom de fichier personnalisé ---
        $extension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
        $baseName = 'accueil_';

        try {
            // Si une date est fournie, on l'utilise, sinon on prend la date/heure actuelle
            $date = new \DateTime($displayUntil ?: 'now');
            $baseName .= $date->format('Ymd_Hi');
        } catch (Exception $e) {
            // En cas de format de date invalide, on se rabat sur la date actuelle
            $baseName .= (new \DateTime())->format('Ymd_Hi');
        }

        // Gérer les versions pour éviter d'écraser des fichiers
        $index = 1;
        $fileName = $baseName . '_' . $index . '.' . $extension;
        $uploadFile = $uploadDir . DIRECTORY_SEPARATOR . $fileName;

        while (file_exists($uploadFile)) {
            $index++;
            $fileName = $baseName . '_' . $index . '.' . $extension;
            $uploadFile = $uploadDir . DIRECTORY_SEPARATOR . $fileName;
        }

        if (move_uploaded_file($fileData['tmp_name'], $uploadFile)) {
            // Retourner l'URL publique de l'image
            return '/images/accueil/' . $fileName;
        }

        throw new Exception('Impossible de déplacer le fichier téléversé.');
    }
}