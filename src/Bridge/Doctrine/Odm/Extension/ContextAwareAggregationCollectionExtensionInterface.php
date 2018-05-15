<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ODM\MongoDB\Aggregation\Builder;

/**
 * Cette interface definit en plus la prise en compte d'un context pour une extension
 * Interface ContextAwareQueryCollectionExtensionInterface.
 */
interface ContextAwareAggregationCollectionExtensionInterface extends AggregationCollectionExtensionInterface
{
    /**
     * @param Builder                     $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string                      $resourceClass
     * @param null|string                 $operationName
     * @param array                       $context
     *
     * @return mixed
     */
    public function applyToCollection(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []);
}
