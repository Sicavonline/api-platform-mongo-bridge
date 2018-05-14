<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension;

use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Interface ContextAwareQueryResultCollectionExtensionInterface for ODM extensions with result like pagination
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension
 */
interface ContextAwareQueryResultCollectionExtensionInterface extends QueryResultCollectionExtensionInterface
{
    /**
     * @param string      $resourceClass
     * @param null|string $operationName
     * @param array       $context
     *
     * @return bool
     */
    public function supportsResult(string $resourceClass, string $operationName = null, array $context = []): bool;

    /**
     * @param Builder     $queryBuilder
     * @param null|string $resourceClass
     * @param null|string $operationName
     * @param array       $context
     *
     * @return mixed
     */
    public function getResult(Builder $queryBuilder, string $resourceClass = null, string $operationName = null, array $context = []);
}
