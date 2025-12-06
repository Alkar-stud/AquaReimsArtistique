<?php

namespace app\Core;

class Paginator
{
    private array $items;
    private int $totalItems;
    private int $itemsPerPage;
    private int $currentPage;
    private int $totalPages;

    public function __construct(array $items, int $totalItems, int $itemsPerPage, int $currentPage)
    {
        $this->items = $items;
        $this->totalItems = $totalItems;
        $this->itemsPerPage = $itemsPerPage;
        $this->currentPage = $currentPage;
        $this->totalPages = ($itemsPerPage > 0) ? (int)ceil($totalItems / $itemsPerPage) : 0;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function hasPages(): bool
    {
        return $this->totalPages > 1;
    }
}