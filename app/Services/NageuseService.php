<?php

namespace app\Services;

use app\Repository\Nageuse\GroupesNageusesRepository;
use app\Repository\Nageuse\NageusesRepository;

class NageuseService
{
    private GroupesNageusesRepository $groupesNageusesRepository;
    private NageusesRepository $nageusesRepository;
    public function __construct()
    {
        $this->groupesNageusesRepository = new GroupesNageusesRepository();
        $this->nageusesRepository = new NageusesRepository();
    }

    public function getAllGroupesNageuses(): array
    {
        return $this->groupesNageusesRepository->findAll();
    }

    public function getNageusesByGroupe(): array
    {
        $nageuses = $this->nageusesRepository->findAll();
        $nageusesParGroupe = [];
        foreach ($nageuses as $nageuse) {
            $groupeId = $nageuse->getGroupe();
            if (!isset($nageusesParGroupe[$groupeId])) {
                $nageusesParGroupe[$groupeId] = [];
            }
            $nageusesParGroupe[$groupeId][] = [
                'id' => $nageuse->getId(),
                'nom' => $nageuse->getName()
            ];
        }
        return $nageusesParGroupe;
    }


}