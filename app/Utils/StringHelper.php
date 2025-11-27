<?php

namespace app\Utils;

use Normalizer;

class StringHelper
{
    public static function slugify(string $text): string
    {
        // Normalisation Unicode (NFD) pour séparer les accents
        if (class_exists('\Normalizer')) {
            $text = Normalizer::normalize($text, Normalizer::FORM_D);
        }
        // Suppression des diacritiques (accents)
        $text = preg_replace('/\p{Mn}/u', '', $text);
        // Remplacement des caractères non alphanumériques par un tiret
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // Suppression des tirets en début/fin et mise en minuscules
        $text = trim($text, '-');
        return strtolower($text);
    }

    public static function toUpperCase(string $str): string {
        $raw = trim($str);

        // Normalisation Unicode si l'extension intl est disponible (recommandé)
        if (extension_loaded('intl') && class_exists(Normalizer::class)) {
            $raw = Normalizer::normalize($raw);
        }
        // Met tout en MAJUSCULES en respectant les accents
        return mb_strtoupper($raw, 'UTF-8');
    }

    public static function toTitleCase(string $str): string {
        $raw = trim($str);

        // Normalisation Unicode si l'extension intl est disponible (recommandé)
        if (extension_loaded('intl') && class_exists(Normalizer::class)) {
            $raw = Normalizer::normalize($raw);
        }
        // Met en majuscule la première lettre de tous les mots
        return mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
    }


}