<?php

namespace app\Utils;

class UploadHelper
{

    /**
     * Pour uploader et retourner le nom du fichier
     *
     * @param array $file
     * @param string $path
     * @return array
     */
    public static function FileToUpload(array $file, int $i): array
    {
        if ($file['error'] === UPLOAD_ERR_OK) {
            // Validation du fichier uploadé
            if ($file['size'] > MAX_UPLOAD_PROOF_SIZE * 1024 * 1024) {
                return ['success' => false, 'error' => "Le justificatif du participant " . ($i + 1) . " dépasse la taille maximale autorisée (2 Mo)."];
            }

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $allowedMimeTypes = ['application/pdf', 'image/jpeg', 'image/png'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $mimeType = mime_content_type($file['tmp_name']);

            if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
                return ['success' => false, 'error' => "Format de justificatif non autorisé pour le participant " . ($i + 1) . " (PDF, JPG, PNG uniquement)."];
            }

            // Génération du nom et déplacement
            $uniqueName = $this->generateUniqueProofName($noms[$i], $prenoms[$i], $tarifId, $extension);
            $uploadDir = __DIR__ . '/../..' . UPLOAD_PROOF_PATH . 'temp/';

            $uploadPath = $uploadDir . $uniqueName;
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // On utilise cette méthode pour obtenir un message d'erreur clair.
                // Si l'erreur n'est pas liée à l'upload (ex : problème de permission sur le dossier de destination),
                // on affiche un message générique.
                $uploadError = $file['error'] !== UPLOAD_ERR_OK ? $this->getUploadErrorMessage($file['error']) : "Impossible de déplacer le fichier.";
                return ['success' => false, 'error' => "Erreur pour le participant " . ($i + 1) . " : " . $uploadError];
            }
        } elseif (!empty($detail['justificatif_name'])) {
            // Conserver le justificatif déjà présent en session
            $uniqueName = $detail['justificatif_name'];
        } else {
            return ['success' => false, 'error' => "Justificatif manquant pour le participant: " . ($i + 1)];
        }



        return ['success' => true, 'file_name' => $uniqueName];
    }


    /**
     * Génère un nom de fichier unique pour un justificatif.
     *
     * @param string $nom
     * @param string $prenom
     * @param int $tarifId
     * @param string $extension
     * @return string
     */
    private function generateUniqueProofName(string $nom, string $prenom, int $tarifId, string $extension): string
    {
        $sessionId = session_id();
        $safeNom = strtolower(preg_replace('/[^a-z0-9]/i', '', $nom));
        $safePrenom = strtolower(preg_replace('/[^a-z0-9]/i', '', $prenom));
        return "{$sessionId}_{$tarifId}_{$safeNom}_{$safePrenom}.{$extension}";
    }


    /**
     * Traduit un code d'erreur d'upload PHP en un message compréhensible.
     *
     * @param int $errorCode Le code d'erreur (ex: UPLOAD_ERR_INI_SIZE).
     * @return string Le message d'erreur correspondant.
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return "Le fichier dépasse la taille maximale autorisée par le serveur.";
            case UPLOAD_ERR_FORM_SIZE:
                return "Le fichier dépasse la taille maximale spécifiée dans le formulaire.";
            case UPLOAD_ERR_PARTIAL:
                return "Le fichier n'a été que partiellement téléchargé.";
            case UPLOAD_ERR_NO_FILE:
                return "Aucun fichier n'a été téléchargé.";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Dossier temporaire manquant sur le serveur.";
            case UPLOAD_ERR_CANT_WRITE:
                return "Échec de l'écriture du fichier sur le disque.";
            case UPLOAD_ERR_EXTENSION:
                return "Une extension PHP a arrêté le téléchargement du fichier.";
            default:
                return "Erreur inconnue lors du téléchargement.";
        }
    }

}