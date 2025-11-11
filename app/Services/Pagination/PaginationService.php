<?php

namespace app\Services\Pagination;

class PaginationService
{
    private const int DEFAULT_ITEMS_PER_PAGE = 20;
    private const array ALLOWED_ITEMS_PER_PAGE = [10, 20, 50, 100];

    /**
     * Crée une configuration de pagination à partir des paramètres de la requête.
     *
     * @param array $queryParams (généralement $_GET)
     * @return PaginationConfig
     */
    public function createFromRequest(array $queryParams): PaginationConfig
    {
        $currentPage = (int)($queryParams['page'] ?? 1);
        if ($currentPage < 1) {
            $currentPage = 1;
        }

        $itemsPerPage = (int)($queryParams['per_page'] ?? self::DEFAULT_ITEMS_PER_PAGE);

        // On s'assure que la valeur est autorisée, sinon on prend la valeur par défaut.
        if (!in_array($itemsPerPage, self::ALLOWED_ITEMS_PER_PAGE)) {
            $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE;
        }

        return new PaginationConfig($currentPage, $itemsPerPage, self::ALLOWED_ITEMS_PER_PAGE);
    }
}