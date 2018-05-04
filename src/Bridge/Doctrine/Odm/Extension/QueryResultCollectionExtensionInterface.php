<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension;

use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Interface QueryResultCollectionExtensionInterface
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension
 */
interface QueryResultCollectionExtensionInterface extends QueryCollectionExtensionInterface
{
    public function supportsResult(string $resourceClass, string $operationName = null): bool;

    /**
     * @return mixed
     */
    public function getResult(Builder $queryBuilder);
}
