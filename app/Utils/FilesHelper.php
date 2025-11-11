<?php

namespace app\Utils;

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

}