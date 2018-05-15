<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ODM\MongoDB\Query\Builder;


/**
 * Interface QueryItemExtensionInterface
 * @package src\Bridge\Doctrine\Odm\Extension
 */
interface QueryItemExtensionInterface
{
    public function applyToItem(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, string $operationName = null, array $context = []);
}
