<?php

namespace app\Services\Pagination;

class PaginationConfig
{
    private int $currentPage;
    private int $itemsPerPage;
    private array $allowedItemsPerPage;

    public function __construct(int $currentPage, int $itemsPerPage, array $allowedItemsPerPage)
    {
        $this->currentPage = $currentPage;
        $this->itemsPerPage = $itemsPerPage;
        $this->allowedItemsPerPage = $allowedItemsPerPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }
}