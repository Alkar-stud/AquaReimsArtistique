<?php

namespace app\Utils;

/**
 * Pour enregistrer les échanges JSON avec HelloAsso
 */
class HelloAssoDebugJson
{
    private string $storageDir;
    public function __construct()
    {
        $this->storageDir = __DIR__ . '/../../storage/app/private/HelloAssoDebug/';
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0770, true);
        }
    }

    /**
     * Enregistre un payload JSON dans un fichier de débogage.
     *
     * @param object|array $payload Le payload décodé (objet ou tableau).
     * @param string $rawPayload Le payload brut en chaîne de caractères.
     * @param string $prefix Un préfixe pour le nom du fichier (ex: 'webhook_callback').
     */
    public function save(object|array $payload, string $rawPayload, string $prefix = 'helloasso_debug'): void
    {
        $filenameParts = [$prefix];

        if (is_object($payload)) {
            if (isset($payload->eventType)) {
                $filenameParts[] = $payload->eventType;
            }
            if (isset($payload->metadata->reservationId)) {
                $filenameParts[] = 'resId-' . $payload->metadata->reservationId;
            }
        }
        $filename = $this->storageDir . implode('_', $filenameParts) . '_' . date('Ymd-His') . '_' . uniqid() . '.json';
        // On essaie de formater le JSON pour la lisibilité, sinon on sauvegarde le texte brut.
        $contentToSave = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $contentToSave = $rawPayload;
        }

        file_put_contents($filename, $contentToSave);
    }
}