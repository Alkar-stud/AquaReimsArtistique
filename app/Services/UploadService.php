<?php

namespace app\Services;

use Exception;

class UploadService
{
    /**
     * Gère le téléversement d'un fichier image pour CKEditor
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

    /**
     * Valide et déplace un fichier uploadé.
     *
     * @param array $file Le tableau de fichier provenant de $_FILES (ex: $_FILES['justificatifs']).
     * @param string $destinationPath Le chemin complet du dossier de destination.
     * @param string $newFileName Le nom final du fichier (sans le chemin).
     * @param array $options Options de validation.
     * @return array ['success' => bool, 'error' => ?string]
     */
    public function handleUpload(array $file, string $destinationPath, string $newFileName, array $options = []): array
    {
        // --- Vérification des erreurs d'upload initiales ---
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'error' => 'Paramètres de fichier invalides.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => $this->getUploadErrorMessage($file['error'])];
        }

        // --- Validation de la taille ---
        $maxSize = ($options['max_size_mb'] ?? 2) * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'error' => "Le fichier dépasse la taille maximale autorisée ({$options['max_size_mb']} Mo)."];
        }

        // --- Validation du type MIME et de l'extension ---
        $allowedExtensions = $options['allowed_extensions'] ?? ['pdf', 'jpg', 'jpeg', 'png'];
        $allowedMimeTypes = $options['allowed_mime_types'] ?? ['application/pdf', 'image/jpeg', 'image/png'];

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $mimeType = mime_content_type($file['tmp_name']);

        if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            return ['success' => false, 'error' => 'Format de fichier non autorisé. Formats permis : ' . implode(', ', $allowedExtensions)];
        }

        // --- Vérification et création du dossier de destination ---
        if (!is_dir($destinationPath) && !mkdir($destinationPath, 0755, true)) {
            return ['success' => false, 'error' => 'Impossible de créer le dossier de destination.'];
        }
        if (!is_writable($destinationPath)) {
            return ['success' => false, 'error' => 'Le dossier de destination n\'est pas accessible en écriture.'];
        }

        // --- Déplacement du fichier ---
        $finalPath = rtrim($destinationPath, '/') . '/' . $newFileName;
        if (!move_uploaded_file($file['tmp_name'], $finalPath)) {
            return ['success' => false, 'error' => 'Échec du déplacement du fichier téléchargé.'];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * Traduit un code d'erreur d'upload PHP en un message compréhensible.
     *
     * @param int $errorCode Le code d'erreur (ex: UPLOAD_ERR_INI_SIZE).
     * @return string Le message d'erreur correspondant.
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => "Le fichier dépasse la taille maximale autorisée par le serveur.",
            UPLOAD_ERR_FORM_SIZE => "Le fichier dépasse la taille maximale spécifiée dans le formulaire.",
            UPLOAD_ERR_PARTIAL => "Le fichier n'a été que partiellement téléchargé.",
            UPLOAD_ERR_NO_FILE => "Aucun fichier n'a été téléchargé.",
            UPLOAD_ERR_NO_TMP_DIR => "Dossier temporaire manquant sur le serveur.",
            UPLOAD_ERR_CANT_WRITE => "Échec de l'écriture du fichier sur le disque.",
            UPLOAD_ERR_EXTENSION => "Une extension PHP a arrêté le téléchargement du fichier.",
            default => "Erreur inconnue lors du téléchargement.",
        };
    }


}