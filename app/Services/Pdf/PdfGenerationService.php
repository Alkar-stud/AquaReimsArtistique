<?php

namespace app\Services\Pdf;

use app\Repository\Reservation\ReservationRepository;
use app\Services\Event\EventQueryService;
use app\Services\Pdf\Types\ListeParticipantsPdf;
use app\Services\Pdf\Types\RecapEvenementPdf;
use app\Services\Pdf\Types\RecapFinalPdf;
use app\Services\Pdf\Types\RecapPlacesA3Pdf;
use app\Services\Pdf\Types\RecapReservationsPdf;
use app\Utils\StringHelper;
use InvalidArgumentException;

readonly class PdfGenerationService
{
    public const array PDF_TYPES = [
        'ListeParticipants' => [
            'label' => 'Liste des participants',
            'builder' => ListeParticipantsPdf::class,
        ],
        'RecapReservations' => [
            'label' => 'Récapitulatif des réservations',
            'builder' => RecapReservationsPdf::class,
        ],
        'RecapEvenement' => [
            'label' => 'Récapitulatif de l\'évènement',
            'builder' => RecapEvenementPdf::class,
        ],
        'RecapPlacesA3' => [
            'label' => 'Pan récapitulatif des places (A3)',
            'builder' => RecapPlacesA3Pdf::class,
        ],
        'RecapFinal' => [
            'label' => 'Récapitulatif de votre réservation',
            'builder' => RecapFinalPdf::class,
        ],
    ];

    public function __construct(
        private EventQueryService     $eventQueryService,
        private ReservationRepository $reservationRepository
    ) {
    }

    /**
     * Point d'entrée principal pour la génération de PDF.
     *
     * @param string $pdfType Le type de PDF à générer (ex: 'ListeParticipants').
     * @param int $sessionId L'ID de la session concernée.
     * @param string $sortOrder L'ordre de tri des données.
     * @return BasePdf L'objet PDF prêt à être envoyé.
     */
    public function generate(string $pdfType, int $sessionId, string $sortOrder): BasePdf
    {
        $title = $this->getNameOfPdf($pdfType, $sessionId);
        $builder = $this->getBuilderForType($pdfType, $title);

        $data = [
            'sessionId' => $sessionId,
            'sortOrder' => $sortOrder,
            'title' => $title,
        ];

        return $builder->build($data);
    }

    /**
     * Point d'entrée pour la génération de PDF unitaire
     *
     * @param string $pdfType Le type de PDF à générer (ex: 'ListeParticipants').
     * @param int $reservationId L'ID de la réservation concernée.
     * @return BasePdf L'objet PDF prêt à être envoyé.
     */
    public function generateUnitPdf(string $pdfType, int $reservationId, array $params = []): BasePdf
    {
        $builder = $this->getBuilderForType($pdfType, '');

        $data = [
            'reservationId' => $reservationId,
            'params' => $params,
        ];

        return $builder->build($data);
    }


    /**
     * Retourne le nom du PDF pour l'export
     *
     * @param $pdfType
     * @param $sessionId
     * @param bool $asFilename
     * @return string
     */
    public function getNameOfPdf($pdfType, $sessionId, bool $asFilename = false): string
    {
        $session = $this->eventQueryService->findSessionById($sessionId);
        if (!$session) {
            throw new \RuntimeException("Session non trouvée pour l'ID: $sessionId");
        }

        if (!isset(self::PDF_TYPES[$pdfType])) {
            throw new InvalidArgumentException("Le type de PDF '$pdfType' n'est pas supporté.");
        }

        $startOfTitleDoc = self::PDF_TYPES[$pdfType]['label'];

        $title = $startOfTitleDoc . $session->getEventObject()->getName() . " - " . $session->getSessionName();

        if ($asFilename) {
            return StringHelper::slugify($title);
        }

        return mb_convert_encoding($title, "ISO-8859-1", "UTF-8");
    }

    private function getBuilderForType(string $pdfType, string $title): PdfTypeInterface
    {
        if (!isset(self::PDF_TYPES[$pdfType])) {
            throw new InvalidArgumentException("Le type de PDF '$pdfType' n'est pas supporté.");
        }

        $builderClass = self::PDF_TYPES[$pdfType]['builder'];

        // On instancie la classe spécialiste requise en lui passant les dépendances nécessaires.
        return new $builderClass($this->eventQueryService, $this->reservationRepository);
    }

    /**
     * Génère un nom de fichier propre pour le PDF.
     *
     * @param string $pdfType
     * @param int $sessionId
     * @return string
     */
    public function getFilenameForPdf(string $pdfType, int $sessionId): string
    {
        $session = $this->eventQueryService->findSessionById($sessionId);
        if (!$session) {
            return 'export-erreur';
        }

        if (!isset(self::PDF_TYPES[$pdfType])) {
            $pdfType = 'document';
        }

        $title = self::PDF_TYPES[$pdfType]['label'] . ' ' . $session->getEventObject()->getName() . " " . $session->getSessionName();
        return StringHelper::slugify($title);
    }
}