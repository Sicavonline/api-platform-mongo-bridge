<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\EagerLoadingTrait;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use Doctrine\ODM\MongoDB\Query\Builder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * Eager loads relations.
 *
 * @author Charles Sarrazin <charles@sarraz.in>
 * @author Kévin Dunglas <dunglas@gmail.com>
 * @author Antoine Bluchet <soyuka@gmail.com>
 * @author Baptiste Meyer <baptiste.meyer@gmail.com>
 */
final class EagerLoadingExtension implements ContextAwareQueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    use EagerLoadingTrait;

    private $propertyNameCollectionFactory;
    private $propertyMetadataFactory;
    private $classMetadataFactory;
    private $maxJoins;
    private $serializerContextBuilder;
    private $requestStack;

    /**
     * @TODO move $fetchPartial after $forceEager (@soyuka) in 3.0
     */
    public function __construct(PropertyNameCollectionFactoryInterface $propertyNameCollectionFactory, PropertyMetadataFactoryInterface $propertyMetadataFactory, ResourceMetadataFactoryInterface $resourceMetadataFactory, int $maxJoins = 30, bool $forceEager = true, RequestStack $requestStack = null, SerializerContextBuilderInterface $serializerContextBuilder = null, bool $fetchPartial = false, ClassMetadataFactoryInterface $classMetadataFactory = null)
    {
        if (null !== $this->requestStack) {
            @trigger_error(sprintf('Passing an instance of "%s" is deprecated since version 2.2 and will be removed in 3.0. Use the data provider\'s context instead.', RequestStack::class), E_USER_DEPRECATED);
        }
        if (null !== $this->serializerContextBuilder) {
            @trigger_error(sprintf('Passing an instance of "%s" is deprecated since version 2.2 and will be removed in 3.0. Use the data provider\'s context instead.', SerializerContextBuilderInterface::class), E_USER_DEPRECATED);
        }

        $this->propertyNameCollectionFactory = $propertyNameCollectionFactory;
        $this->propertyMetadataFactory       = $propertyMetadataFactory;
        $this->resourceMetadataFactory       = $resourceMetadataFactory;
        $this->classMetadataFactory          = $classMetadataFactory;
        $this->maxJoins                      = $maxJoins;
        $this->forceEager                    = $forceEager;
        $this->fetchPartial                  = $fetchPartial;
        $this->serializerContextBuilder      = $serializerContextBuilder;
        $this->requestStack                  = $requestStack;
    }

    /**
     * {@inheritdoc}
     */
    public function applyToCollection(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass = null, string $operationName = null, array $context = []): void
    {
        $this->apply(true, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
    }

    /**
     * The context may contain serialization groups which helps defining joined entities that are readable.
     */
    public function applyToItem(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, string $operationName = null, array $context = []): void
    {
        $this->apply(false, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
    }

    private function apply(bool $collection, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass = null, string $operationName = null, array $context): void
    {
        if (null === $resourceClass) {
            throw new InvalidArgumentException('The "$resourceClass" parameter must not be null');
        }

        $queryBuilder->eagerCursor(true);
    }
}
