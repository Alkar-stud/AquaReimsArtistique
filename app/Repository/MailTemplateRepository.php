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
}