<?php

namespace app\Repository\Mail;

use app\Models\Mail\MailTemplate;
use app\Repository\AbstractRepository;

class MailTemplateRepository extends AbstractRepository
{
    public function __construct()
    {
        parent::__construct('mail_template');
    }

    /**
     * Retourne tous les templates triés par code.
     * @return MailTemplate[]
     */
    public function findAll(): array
    {
        $sql = "SELECT * FROM $this->tableName ORDER BY code";
        $results = $this->query($sql);
        return array_map([$this, 'hydrate'], $results);
    }

    /**
     * Trouve un template par son ID.
     * @param int $id
     * @return MailTemplate|null
     */
    public function findById(int $id): ?MailTemplate
    {
        $sql = "SELECT * FROM $this->tableName WHERE id = :id";
        $results = $this->query($sql, ['id' => $id]);
        return $results ? $this->hydrate($results[0]) : null;
    }

    /**
     * Trouve un template par son code.
     * @param string $code
     * @return MailTemplate|null
     */
    public function findByCode(string $code): ?MailTemplate
    {
        $sql = "SELECT * FROM $this->tableName WHERE code = :code";
        $results = $this->query($sql, ['code' => $code]);
        return $results ? $this->hydrate($results[0]) : null;
    }


    /**
     * Retourne les templates dont le code est dans la liste fournie.
     * @param string[] $codes
     * @return MailTemplate[]
     */
    public function findByCodes(array $codes): array
    {
        // Nettoyage: uniques, strings non vides
        $codes = array_values(array_unique(array_filter(
            $codes,
            static fn($c) => is_string($c) && $c !== ''
        )));
        if (!$codes) {
            return [];
        }

        // Placeholders nommés pour le IN (...)
        $placeholders = [];
        $params = [];
        foreach ($codes as $i => $code) {
            $key = "code_$i";
            $placeholders[] = ":$key";
            $params[$key] = $code;
        }

        $in = implode(',', $placeholders);
        $sql = "SELECT * FROM $this->tableName WHERE code IN ($in) ORDER BY code";
        $rows = $this->query($sql, $params);

        return array_map([$this, 'hydrate'], $rows);
    }

    /**
     * Insère un nouveau template.
     * @return int ID inséré (0 si échec)
     */
    public function insert(MailTemplate $mailTemplate): int
    {
        $sql = "INSERT INTO $this->tableName (code, subject, body_html, body_text)
                VALUES (:code, :subject, :body_html, :body_text)";
        $ok = $this->execute($sql, [
            'code' => $mailTemplate->getCode(),
            'subject' => $mailTemplate->getSubject(),
            'body_html' => $mailTemplate->getBodyHtml(),
            'body_text' => $mailTemplate->getBodyText(),
        ]);
        return $ok ? $this->getLastInsertId() : 0;
    }

    /**
     * Met à jour un template existant.
     * @param MailTemplate $mailTemplate
     * @return bool
     */
    public function update(MailTemplate $mailTemplate): bool
    {
        $sql = "UPDATE $this->tableName
                SET code = :code, subject = :subject, body_html = :body_html, body_text = :body_text, updated_at = NOW()
                WHERE id = :id";
        return $this->execute($sql, [
            'id' => $mailTemplate->getId(),
            'code' => $mailTemplate->getCode(),
            'subject' => $mailTemplate->getSubject(),
            'body_html' => $mailTemplate->getBodyHtml(),
            'body_text' => $mailTemplate->getBodyText(),
        ]);
    }

    /**
     * Hydrate un objet MailTemplate à partir d'une ligne BDD.
     * @param array $data
     * @return MailTemplate
     */
    protected function hydrate(array $data): MailTemplate
    {
        $template = new MailTemplate();
        $template->setId((int)$data['id'])
            ->setCode($data['code'])
            ->setSubject($data['subject'])
            ->setBodyHtml($data['body_html'])
            ->setBodyText($data['body_text']);

        return $template;
    }
}
