<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Class OrderExtension
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension
 */
class OrderExtension implements ContextAwareQueryCollectionExtensionInterface
{
    /**
     * @var string
     */
    private $order;

    /**
     * @var ResourceMetadataFactoryInterface
     */
    private $resourceMetadataFactory;

    /**
     * OrderExtension constructor.
     *
     * @param null|string                           $order                   l'ordre injecte par api plateform
     * @param null|ResourceMetadataFactoryInterface $resourceMetadataFactory le meta data factory d'api plateform
     */
    public function __construct(string $order = null, ResourceMetadataFactoryInterface $resourceMetadataFactory = null)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->order                   = $order;
    }

    /** {@inheritdoc} */
    public function applyToCollection(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (null === $resourceClass) {
            throw new InvalidArgumentException('The "$resourceClass" parameter must not be null');
        }

        // les ordres depuis les meta data de la classe
        $classMetaData = $queryBuilder->getQuery()->getDocumentManager()->getClassMetadata($resourceClass);
        $identifiers   = $classMetaData->getIdentifier();
        if (null !== $this->resourceMetadataFactory) {
            $defaultOrder = $this->resourceMetadataFactory->create($resourceClass)->getAttribute('order');
            if (null !== $defaultOrder) { // si il existe une anotation order
                foreach ($defaultOrder as $field => $order) {
                    if (\is_int($field)) {
                        $field = $order;
                        $order = 'ASC';
                    }
                    $queryBuilder->sort($field, $order);
                }

                return;
            }
        }

        if (null !== $this->order) { // order par defaut inject� par api-plateform
            foreach ($identifiers as $identifier) {
                $queryBuilder->sort("$identifier", $this->order);
            }
        }
    }
}
