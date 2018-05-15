<?php

namespace App\Doctrine\Odm\Paginator\Decorator\Decorator;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Paginator\DoctrineMongoPaginator;
use Doctrine\MongoDB\Query\Query;

/**
 * Class PaginatorPartialDecorator
 * @package App\Doctrine\Odm\Paginator\Decorator\Decorator
 */
class PaginatorPartialDecorator implements \IteratorAggregate
{
    protected $paginator;
    protected $iterator;
    protected $firstResult;
    protected $maxResults;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(DoctrineMongoPaginator $paginator)
    {
        $query = $paginator->getQuery();

        $firstResult = isset($query->getQuery()['skip']) ? $query->getQuery()['skip'] : null;
        $maxResults  = isset($query->getQuery()['limit']) ? $query->getQuery()['limit'] : null;
        if (null === $firstResult || null === $maxResults) {
            throw new InvalidArgumentException(sprintf('"%1$s::setFirstResult()" or/and "%1$s::setMaxResults()" was/were not applied to the query.', Query::class));
        }

        $this->paginator   = $paginator;
        $this->firstResult = $firstResult;
        $this->maxResults  = $maxResults;
    }

    public function getCurrentPage(): float
    {
        if (0 >= $this->maxResults) {
            return 1.;
        }

        return floor($this->firstResult / $this->maxResults) + 1.;
    }

    public function getItemsPerPage(): float
    {
        return (float) $this->maxResults;
    }

    public function getIterator(): \Traversable
    {
        return $this->iterator ?? $this->iterator = $this->paginator->getIterator();
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function toArray()
    {
        return array_values(iterator_to_array($this->getIterator()));
    }
}
