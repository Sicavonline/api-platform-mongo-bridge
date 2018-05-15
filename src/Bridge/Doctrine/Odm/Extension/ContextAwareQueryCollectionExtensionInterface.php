<?php

namespace Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Interface ContextAwareQueryCollectionExtensionInterface for ODM context aware extension
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension
 */
interface ContextAwareQueryCollectionExtensionInterface extends QueryCollectionExtensionInterface
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
