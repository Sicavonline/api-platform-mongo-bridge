<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter;

use ApiPlatform\Core\Api\FilterInterface as BaseFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Interface FilterInterface
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter
 */
interface FilterInterface extends BaseFilterInterface
{
    /**
     * Applies the filter.
     */
    public function apply(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null);
}
