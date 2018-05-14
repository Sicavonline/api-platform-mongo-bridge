<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Class AbstractContextAwareFilter
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter
 */
abstract class AbstractContextAwareFilter extends AbstractFilter implements ContextAwareFilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (!isset($context['filters']) || !\is_array($context['filters'])) {
            parent::apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);

            return;
        }

        foreach ($context['filters'] as $property => $value) {
            $this->filterProperty($property, $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
        }
    }
}
