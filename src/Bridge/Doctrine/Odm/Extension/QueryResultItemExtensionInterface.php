<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension;


use Doctrine\ODM\MongoDB\Query\Builder;

interface QueryResultItemExtensionInterface extends QueryItemExtensionInterface
{

    public function supportsResult(string $resourceClass, string $operationName = null): bool;

    /**
     * @return mixed
     */
    public function getResult(Builder $queryBuilder);
}
