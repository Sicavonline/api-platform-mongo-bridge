<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Paginator\Decorator;
use Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Paginator\Decorator\PaginatorPartialDecorator;

/**
 * Class PaginatorDecorator
 * @package Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Paginator\Decorator
 */
class PaginatorDecorator extends PaginatorPartialDecorator
{
    /**
     * @var int
     */
    private $totalItems;

    /**
     * {@inheritdoc}
     */
    public function getLastPage(): float
    {
        if (0 >= $this->maxResults) {
            return 1.;
        }

        return ceil($this->getTotalItems() / $this->maxResults) ?: 1.;
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalItems(): float
    {
        return (float) ($this->totalItems ?? $this->totalItems = \count($this->paginator));
    }
}
