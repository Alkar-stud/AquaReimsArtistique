<?php
namespace app\Services\Csv;

use app\Utils\FilesHelper;

readonly class CsvGenerationService
{
    /**
     * Génère le fichier CSV et retourne son chemin.
     *
     * @param array $headerFields
     * @param array $data
     * @return string Chemin absolu du fichier généré
     */
    public function generate(array $headerFields, array $data = []): string
    {
        $filename = 'export_reservations_' . date('Ymd_His');

        $csvBuilder = new BaseCsv(
            pathDocument: 'storage/private/exports/',
            nameFile: $filename,
            header: $headerFields
        );

        return $csvBuilder->generate($headerFields, $data);
    }

    /**
     * Force le téléchargement puis supprime le fichier.
     *
     * @param string $fullPath
     * @param string $downloadName
     * @return void
     */
    public function sendAndCleanup(string $fullPath, string $downloadName): void
    {
        if (!is_file($fullPath)) {
            http_response_code(404);
            exit('Fichier introuvable.');
        }

        $filesHelper = new FilesHelper();
        $filesHelper->forcerTelechargement($downloadName, $fullPath, (int)filesize($fullPath));

        // Sécurité (normalement exit dans forcerTelechargement)
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}
