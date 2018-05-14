<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Interface QueryCollectionExtensionInterface
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension
 */
interface QueryCollectionExtensionInterface
{
    /**
     * @param Builder                     $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string                      $resourceClass
     * @param null|string                 $operationName
     *
     * @return mixed
     */
    public function applyToCollection(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null);
}
