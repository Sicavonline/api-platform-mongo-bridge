<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Api\FilterCollection;
use ApiPlatform\Core\Api\FilterLocatorTrait;
use Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use Psr\Container\ContainerInterface;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Class FilterExtension
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension
 */
final class FilterExtension implements ContextAwareQueryCollectionExtensionInterface
{
    use FilterLocatorTrait;

    private $resourceMetadataFactory;

    /**
     * @param ContainerInterface|FilterCollection $filterLocator The new filter locator or the deprecated filter collection
     */
    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory, $filterLocator)
    {
        $this->setFilterLocator($filterLocator);

        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function applyToCollection(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass = null, string $operationName = null, array $context = []): void
    {
        if (null === $resourceClass) {
            throw new InvalidArgumentException('The "$resourceClass" parameter must not be null');
        }

        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
        $resourceFilters  = $resourceMetadata->getCollectionOperationAttribute($operationName, 'filters', [], true);

        if (empty($resourceFilters)) {
            return;
        }

        foreach ($resourceFilters as $filterId) {
            if (!($filter = $this->getFilter($filterId)) instanceof FilterInterface) {// si le filtre n'implemente pas l'interface FilterInterface de app, on prend pas
                continue;
            }

            $context['filters'] = $context['filters'] ?? [];

            /* @var $filter FilterInterface */
            $filter->apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
        }
    }
}
