<?php

namespace app\Utils;

class StringHelper
{
    public static function slugify(string $text): string
    {
        // Normalisation Unicode (NFD) pour séparer les accents
        if (class_exists('\Normalizer')) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D);
        }
        // Suppression des diacritiques (accents)
        $text = preg_replace('/\p{Mn}/u', '', $text);
        // Remplacement des caractères non alphanumériques par un tiret
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // Suppression des tirets en début/fin et mise en minuscules
        $text = trim($text, '-');
        return strtolower($text);
    }
}