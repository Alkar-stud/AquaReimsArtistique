<?php

namespace app\Repository;

use app\Models\MailTemplate;
use DateMalformedStringException;

class MailTemplateRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('mails_templates');
    }

    /**
     * Trouve un template d'email par son code unique.
     *
     * @param string $code Le code du template (ex: 'password_reset')
     * @return MailTemplate|null Le template ou null s'il n'est pas trouvé.
     * @throws DateMalformedStringException
     */
    public function findByCode(string $code): ?MailTemplate
    {
        $sql = "SELECT * FROM $this->tableName WHERE code = :code";
        $results = $this->query($sql, ['code' => $code]);
        // On retourne un objet hydraté ou null
        return $results ? $this->hydrate($results[0]) : null;
    }

    /*
     * retourne tous les templates d'email triés par code
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY code ASC";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Crée et remplit un objet MailTemplate à partir d'un tableau de données.
     * @throws DateMalformedStringException
     */
    private function hydrate(array $data): MailTemplate
    {
        $template = new MailTemplate();
        $template->setId($data['id'])
            ->setCode($data['code'])
            ->setSubject($data['subject'])
            ->setBodyHtml($data['body_html'])
            ->setBodyText($data['body_text'])
            ->setCreatedAt($data['created_at'])
            ->setUpdatedAt($data['updated_at']);

        return $template;
    }

    /**
     * Ajoute un nouveau template mail.
     */
    public function insert(MailTemplate $mailTemplate): void
    {
        $sql = "INSERT INTO $this->tableName 
        (code, subject) 
        VALUES (:code, :subject)";
        $this->execute($sql, [
            'code' => $mailTemplate->getCode(),
            'subject' => $mailTemplate->getSubject(),
        ]);
    }

    public function updateTemplate(int $id, string $subject, ?string $body_html, ?string $body_text): void
    {
        $sql = "UPDATE $this->tableName SET subject = :subject, body_html = :body_html, body_text = :body_text WHERE id = :id";
        $this->query($sql, [
            'subject' => $subject,
            'body_html' => $body_html,
            'body_text' => $body_text,
            'id' => $id
        ]);
    }

    public function deleteById(int $id): void
    {
        $sql = "DELETE FROM $this->tableName WHERE id = :id";
        $this->query($sql, ['id' => $id]);
    }
}