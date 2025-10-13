<?php

namespace app\Services\DataValidation;

class MailTemplateDataValidationService
{
    private ?string $code = null;
    private ?string $subject = null;
    private ?string $body_html = null;
    private ?string $body_text = null;

    /**
     * Validation pour l'ajout (code requis).
     * @param array $data
     * @return string[] liste d'erreurs
     */
    public function validateForAdd(array $data): array
    {
        $this->code = isset($data['code']) ? trim((string)$data['code']) : null;
        $errors = $this->getArr($data);

        // code
        if ($this->code === null || $this->code === '') {
            $errors[] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Z0-9][A-Z0-9._-]*$/i', $this->code)) {
            $errors[] = 'Le code ne doit contenir que des lettres, chiffres, ".", "-", "_", et commencer par une lettre ou un chiffre.';
        } elseif (mb_strlen($this->code) > 80) {
            $errors[] = 'Le code ne doit pas dépasser 80 caractères.';
        }

        // subject
        if ($this->subject === null || $this->subject === '') {
            $errors[] = 'Le sujet est obligatoire.';
        } elseif (mb_strlen($this->subject) > 255) {
            $errors[] = 'Le sujet ne doit pas dépasser 255 caractères.';
        }

        return $errors;
    }

    /**
     * Validation pour l'édition (code non modifié ici).
     * @param array $data
     * @return string[] liste d'erreurs
     */
    public function validateForEdit(array $data): array
    {
        $errors = $this->getArr($data);

        if ($this->subject === null || $this->subject === '') {
            $errors[] = 'Le sujet est obligatoire.';
        } elseif (mb_strlen($this->subject) > 255) {
            $errors[] = 'Le sujet ne doit pas dépasser 255 caractères.';
        }

        return $errors;
    }

    public function getCode(): ?string { return $this->code; }
    public function getSubject(): ?string { return $this->subject; }
    public function getBodyHtml(): ?string { return $this->body_html; }
    public function getBodyText(): ?string { return $this->body_text; }

    /**
     * @param array $data
     * @return array
     */
    private function getArr(array $data): array
    {
        $this->subject = isset($data['subject']) ? trim((string)$data['subject']) : null;
        $this->body_html = (isset($data['body_html']) && trim((string)$data['body_html']) !== '') ? (string)$data['body_html'] : null;
        $this->body_text = (isset($data['body_text']) && trim((string)$data['body_text']) !== '') ? (string)$data['body_text'] : null;

        return [];
    }
}
