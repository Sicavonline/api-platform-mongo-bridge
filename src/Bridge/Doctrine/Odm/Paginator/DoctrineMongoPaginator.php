<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Paginator;

use Doctrine\MongoDB\Query\Builder;

/**
 * Class DoctrineMongoPaginator
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Paginator
 */
class DoctrineMongoPaginator implements \Countable, \IteratorAggregate
{
    protected $query;

    /**
     * DoctrineMongoPaginator constructor.
     *
     * @param Builder $query
     */
    public function __construct(Builder $query)
    {
        if ($query instanceof Builder) {
            $query = $query->getQuery();
        }

        $this->query = $query;
    }

    /**
     * Retourne l'iterator Mongo.
     *
     * @return \Doctrine\MongoDB\Iterator
     */
    public function getIterator()
    {
        return $this->query->getIterator();
    }

    /**
     * Retourne le count de la requ�te.
     *
     * @return mixed
     */
    public function count()
    {
        return $this->query->getIterator()->count();
    }

    /**
     * @return Builder|\Doctrine\MongoDB\Query\Query
     */
    public function getQuery()
    {
        return $this->query;
    }
}
