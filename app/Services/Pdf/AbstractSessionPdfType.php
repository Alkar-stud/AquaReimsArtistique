<?php

namespace app\Services\Pdf;

use app\Models\Event\EventSession;
use app\Services\Event\EventQueryService;
use RuntimeException;

abstract readonly class AbstractSessionPdfType
{
    protected EventQueryService $eventQueryService;

    public function __construct(EventQueryService $eventQueryService)
    {
        $this->eventQueryService = $eventQueryService;
    }

    /**
     * Récupère et valide la session depuis $data.
     *
     * @param array $data
     * @return EventSession
     */
    protected function requireSession(array $data): EventSession
    {
        $sessionId = (int)($data['sessionId'] ?? 0);
        $session = $this->eventQueryService->findSessionById($sessionId);

        if (!$session) {
            throw new RuntimeException("Session non trouvée pour l'ID: $sessionId");
        }

        return $session;
    }
}
