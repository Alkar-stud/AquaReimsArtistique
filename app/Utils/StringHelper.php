<?php

namespace app\Utils;

use DateTimeImmutable;
use Normalizer;

class StringHelper
{
    /**
     * @param string $text
     * @return string
     */
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

    /**
     * @param string $str
     * @return string
     */
    public static function toUpperCase(string $str): string {
        $raw = trim($str);

        // Normalisation Unicode si l'extension intl est disponible (recommandé)
        if (extension_loaded('intl') && class_exists(Normalizer::class)) {
            $raw = Normalizer::normalize($raw);
        }
        // Met tout en MAJUSCULES en respectant les accents
        return mb_strtoupper($raw, 'UTF-8');
    }

    /**
     * @param string $str
     * @return string
     */
    public static function toTitleCase(string $str): string {
        $raw = trim($str);

        // Normalisation Unicode si l'extension intl est disponible (recommandé)
        if (extension_loaded('intl') && class_exists(Normalizer::class)) {
            $raw = Normalizer::normalize($raw);
        }
        // Met en majuscule la première lettre de tous les mots
        return mb_convert_case($raw, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Génère un nom de fichier unique pour un fichier.
     *
     * @param string $name
     * @param string $firstname
     * @param int $tarifId
     * @param string $extension
     * @return string
     */
    public static function generateUniqueProofName(string $name, string $firstname, int $tarifId, string $extension): string
    {
        // Horodatage au fuseau horaire de l'application (ex : 20251005130632)
        $now = new DateTimeImmutable('now');
        $timestamp = $now->format('YmdHis');

        $normalizer = Normalizer::class;
        $name = $normalizer::normalize($name, Normalizer::FORM_D);
        $firstname = $normalizer::normalize($firstname, Normalizer::FORM_D);
        $safeNom = strtolower(preg_replace('/[^a-z0-9]/i', '', $name));
        $safePrenom = strtolower(preg_replace('/[^a-z0-9]/i', '', $firstname));

        return "{$timestamp}_{$tarifId}_{$safeNom}_$safePrenom.$extension";
    }

    /**
     * Génère un numéro de réservation formaté
     *
     * @param int $number
     * @return string
     */

    public static function generateReservationNumber(int $number): string
    {
        return 'ARA-' . str_pad($number, 5, '0', STR_PAD_LEFT);
    }
}