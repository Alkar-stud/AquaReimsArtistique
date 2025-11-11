<?php

namespace app\Utils;

class DataHelper
{
    /**
     * Récupère les données envoyées en POST et vérifie si la/les clés recherchées sont présentes
     *
     * @param array $keyToCheck
     * @return array|null
     */
    public function getAndCheckPostData(array $keyToCheck = []): ?array
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];

        //On vérifie que c'est bien un tableau
        if (!is_array($data)) {
            $this->json(['success' => false, 'message' => 'Données invalides.']);
            return null;
        }

        //S'il n'y a rien à vérifier, on retourne les données
        if (empty($keyToCheck)) {
            return $data;
        }

        //On vérifie si la ou les clés recherchées sont contenues dans $data
        foreach ($keyToCheck as $key) {
            if (!is_string($key) || !array_key_exists($key, $data)) {
                $this->json(['success' => false, 'message' => 'Données manquantes.']);
                return null;
            }
        }

        return $data;
    }

}