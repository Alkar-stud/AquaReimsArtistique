<?php

namespace app\Controllers\Piscine;

use app\Attributes\Route;
use app\Controllers\AbstractController;
use app\Repository\Piscine\PiscineGradinsPlacesRepository;
use app\Repository\Piscine\PiscineGradinsZonesRepository;
use app\Repository\Piscine\PiscineRepository;
use app\Services\Piscine\SeatingPlanService;

class PiscineGradinsController extends AbstractController
{
    private SeatingPlanService $seatingPlanService;
    private PiscineGradinsZonesRepository $piscineGradinsZoneRepository;
    private PiscineGradinsPlacesRepository $piscineGradinsPlacesRepository;
    private PiscineRepository $piscineRepository;

    public function __construct(
        PiscineRepository $piscineRepository,
        PiscineGradinsZonesRepository $piscineGradinsZoneRepository,
        PiscineGradinsPlacesRepository $piscineGradinsPlacesRepository,
    )
    {
        parent::__construct(true);
        $this->piscineGradinsZoneRepository = $piscineGradinsZoneRepository;
        $this->piscineGradinsPlacesRepository = $piscineGradinsPlacesRepository;
        $this->piscineRepository = $piscineRepository;
        $this->seatingPlanService = new SeatingPlanService();
    }

    /**
     * Pour afficher tous les sièges d'une zone de gradin
     * GET /piscine/gradins/123, 0 pour all
     *
     * @param int $piscineId
     * @param int $zoneId
     */
    #[Route('/piscine/gradins/{piscineId}/{zoneId}', name: 'get_piscine_gradins', methods: ['GET'])]
    public function toDisplayBleacherEmpty(int $piscineId, int $zoneId): void
    {
        if ($piscineId < 0) {
            $this->json([
                'success' => false,
                'error' => 'Cette piscine n\'existe pas'
            ]);
        }
        //On récupère la piscine
        $piscine = $this->piscineRepository->findById($piscineId);

        // Liste de toutes les zones
        $zone = $this->piscineGradinsZoneRepository->findById($zoneId);

        $plan = ['zone' => null, 'rows' => [], 'cols' => 0];
        if ($zone) {
            $plan = $this->seatingPlanService->getZonePlan($zone);
        }

        $this->json([
            'success' => true,
            'selected_zone_id' => $zoneId,
            'plan' => $plan,
            'seatState' => [],
        ]);
    }
}
