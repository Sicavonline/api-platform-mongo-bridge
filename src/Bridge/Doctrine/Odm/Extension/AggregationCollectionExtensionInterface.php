<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ODM\MongoDB\Aggregation\Builder;

/**
 * Cette interface definit un contrat de modification de requete doctrine par une extension
 * Interface QueryCollectionExtensionInterface.
 */
interface AggregationCollectionExtensionInterface
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
