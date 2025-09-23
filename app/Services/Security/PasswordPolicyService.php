<?php

namespace app\Services\Security;

class PasswordPolicyService
{
    private const int MIN_LENGTH = 10;
    private const true REQUIRE_UPPERCASE = true;
    private const true REQUIRE_LOWERCASE = true;
    private const true REQUIRE_NUMBER = true;
    private const true REQUIRE_SPECIAL_CHAR = true;

    /**
     * Valide un mot de passe contre la politique de sécurité définie.
     *
     * @param string $password Le mot de passe à valider.
     * @return string[] Un tableau des messages d'erreur. Vide si le mot de passe est valide.
     */
    public function validate(string $password): array
    {
        $errors = [];

        if (mb_strlen($password) < self::MIN_LENGTH) {
            $errors[] = "Le mot de passe doit contenir au moins " . self::MIN_LENGTH . " caractères.";
        }

        if (self::REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre majuscule.";
        }

        if (self::REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins une lettre minuscule.";
        }

        if (self::REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Le mot de passe doit contenir au moins un chiffre.";
        }

        if (self::REQUIRE_SPECIAL_CHAR && !preg_match('/[\W_]/', $password)) { // \W est tout ce qui n'est pas une lettre, un chiffre ou _
            $errors[] = "Le mot de passe doit contenir au moins un caractère spécial (ex: @, #, $, !).";
        }

        return $errors;
    }

    /**
     * Retourne la liste des règles sous forme de texte pour l'affichage.
     *
     * @return string[]
     */
    public function getRulesAsText(): array
    {
        return [
            "Au moins " . self::MIN_LENGTH . " caractères",
            "Au moins une majuscule et une minuscule",
            "Au moins un chiffre",
            "Au moins un caractère spécial"
        ];
    }
}