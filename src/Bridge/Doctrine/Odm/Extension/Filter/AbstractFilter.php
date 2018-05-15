<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\AbstractContextAwareFilter;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Util\RequestParser;
use Doctrine\Bundle\MongoDBBundle\Logger\LoggerInterface;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Types\Type;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class AbstractFilter
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension\Filter
 */
abstract class AbstractFilter implements FilterInterface
{
    protected $managerRegistry;
    protected $requestStack;
    protected $logger;
    protected $properties;

    /**
     * AbstractFilter constructor.
     * @param ManagerRegistry $managerRegistry
     * @param RequestStack|null  $requestStack
     * @param LoggerInterface|null $logger
     * @param array|null $properties
     */
    public function __construct(ManagerRegistry $managerRegistry, $requestStack = null, LoggerInterface $logger = null, array $properties = null)
    {
        if (null !== $requestStack) {
            @trigger_error(sprintf('Passing an instance of "%s" is deprecated since 2.2. Use "filters" context key instead.', RequestStack::class), E_USER_DEPRECATED);
        }

        $this->managerRegistry = $managerRegistry;
        $this->requestStack    = $requestStack;
        $this->logger          = $logger ?? new NullLogger();
        $this->properties      = $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        @trigger_error(sprintf('Using "%s::apply()" is deprecated since 2.2. Use "%s::apply()" with the "filters" context key instead.', __CLASS__, AbstractContextAwareFilter::class), E_USER_DEPRECATED);

        if (null === $this->requestStack || null === $request = $this->requestStack->getCurrentRequest()) {
            return;
        }

        foreach ($this->extractProperties($request, $resourceClass) as $property => $value) {
            $this->filterProperty($property, $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName);
        }
    }

    /**
     * Passes a property through the filter.
     *
     * @param mixed $value
     */
    abstract protected function filterProperty(string $property, $value, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []);

    /**
     * Gets class metadata for the given resource.
     *
     * @param string $resourceClass
     *
     * @return ClassMetadata
     */
    protected function getClassMetadata(string $resourceClass): ClassMetadata
    {
        return $this
            ->managerRegistry
            ->getManagerForClass($resourceClass)
            ->getClassMetadata($resourceClass);
    }

    /**
     * Determines whether the given property is enabled.
     *
     * @param string $property
     *
     * @return bool
     */
    protected function isPropertyEnabled(string $property/*, string $resourceClass*/): bool
    {
        if (\func_num_args() > 1) {
            $resourceClass = func_get_arg(1);
        } else {
            if (__CLASS__ !== \get_class($this)) {
                $r = new \ReflectionMethod($this, __FUNCTION__);
                if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                    @trigger_error(sprintf('Method %s() will have a second `$resourceClass` argument in version API Platform 3.0. Not defining it is deprecated since API Platform 2.1.', __FUNCTION__), E_USER_DEPRECATED);
                }
            }
            $resourceClass = null;
        }

        if (null === $this->properties) {
            // to ensure sanity, nested properties must still be explicitly enabled
            return !$this->isPropertyNested($property, $resourceClass);
        }

        return array_key_exists($property, $this->properties);
    }

    /**
     * Determines whether the given property is mapped.
     *
     * @param string $property
     * @param string $resourceClass
     * @param bool   $allowAssociation
     *
     * @return bool
     */
    protected function isPropertyMapped(string $property, string $resourceClass, bool $allowAssociation = false): bool
    {
        if ($this->isPropertyNested($property, $resourceClass)) {
            $propertyParts = $this->splitPropertyParts($property, $resourceClass);
            $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);
            $property      = $propertyParts['field'];
        } else {
            $metadata = $this->getClassMetadata($resourceClass);
        }

        return $metadata->hasField($property) || ($allowAssociation && $metadata->hasAssociation($property));
    }

    /**
     * Determines whether the given property is nested.
     *
     * @param string $property
     *
     * @return bool
     */
    protected function isPropertyNested(string $property/*, string $resourceClass*/): bool
    {
        if (\func_num_args() > 1) {
            $resourceClass = (string) func_get_arg(1);
        } else {
            if (__CLASS__ !== \get_class($this)) {
                $r = new \ReflectionMethod($this, __FUNCTION__);
                if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                    @trigger_error(sprintf('Method %s() will have a second `$resourceClass` argument in version API Platform 3.0. Not defining it is deprecated since API Platform 2.1.', __FUNCTION__), E_USER_DEPRECATED);
                }
            }
            $resourceClass = null;
        }

        if (false === $pos = strpos($property, '.')) {
            return false;
        }

        return null !== $resourceClass && $this->getClassMetadata($resourceClass)->hasAssociation(substr($property, 0, $pos));
    }

    /**
     * Determines whether the given property is embedded.
     *
     * @param string $property
     * @param string $resourceClass
     *
     * @return bool
     */
    protected function isPropertyEmbedded(string $property, string $resourceClass): bool
    {
        return false !== strpos($property, '.') && $this->getClassMetadata($resourceClass)->hasField($property);
    }

    /**
     * Gets nested class metadata for the given resource.
     *
     * @param string   $resourceClass
     * @param string[] $associations
     *
     * @return ClassMetadata
     */
    protected function getNestedMetadata(string $resourceClass, array $associations): ClassMetadata
    {
        $metadata = $this->getClassMetadata($resourceClass);

        foreach ($associations as $association) {
            if ($metadata->hasAssociation($association)) {
                $associationClass = $metadata->getAssociationTargetClass($association);

                $metadata = $this
                    ->managerRegistry
                    ->getManagerForClass($associationClass)
                    ->getClassMetadata($associationClass);
            }
        }

        return $metadata;
    }

    /**
     * Splits the given property into parts.
     *
     * Returns an array with the following keys:
     *   - associations: array of associations according to nesting order
     *   - field: string holding the actual field (leaf node)
     *
     * @param string $property
     *
     * @return array
     */
    protected function splitPropertyParts(string $property/*, string $resourceClass*/): array
    {
        $parts = explode('.', $property);

        if (\func_num_args() > 1) {
            $resourceClass = func_get_arg(1);
        } else {
            if (__CLASS__ !== \get_class($this)) {
                $r = new \ReflectionMethod($this, __FUNCTION__);
                if (__CLASS__ !== $r->getDeclaringClass()->getName()) {
                    @trigger_error(sprintf('Method %s() will have a second `$resourceClass` argument in version API Platform 3.0. Not defining it is deprecated since API Platform 2.1.', __FUNCTION__), E_USER_DEPRECATED);
                }
            }
        }

        if (!isset($resourceClass)) {
            return [
                'associations' => \array_slice($parts, 0, -1),
                'field'        => end($parts),
            ];
        }

        $metadata = $this->getClassMetadata($resourceClass);
        $slice    = 0;

        foreach ($parts as $part) {
            if ($metadata->hasAssociation($part)) {
                $metadata = $this->getClassMetadata($metadata->getAssociationTargetClass($part));
                ++$slice;
            }
        }

        if ($slice === \count($parts)) {
            --$slice;
        }

        return [
            'associations' => \array_slice($parts, 0, $slice),
            'field'        => implode('.', \array_slice($parts, $slice)),
        ];
    }

    /**
     * Extracts properties to filter from the request.
     *
     * @param Request $request
     *
     * @return array
     */
    protected function extractProperties(Request $request/*, string $resourceClass*/): array
    {
        @trigger_error(sprintf('The use of "%s::extractProperties()" is deprecated since 2.2. Use the "filters" key of the context instead.', __CLASS__), E_USER_DEPRECATED);

        $resourceClass = \func_num_args() > 1 ? (string) func_get_arg(1) : null;
        $needsFixing   = false;
        if (null !== $this->properties) {
            foreach ($this->properties as $property => $value) {
                if (($this->isPropertyNested($property, $resourceClass) || $this->isPropertyEmbedded($property, $resourceClass)) && $request->query->has(str_replace('.', '_', $property))) {
                    $needsFixing = true;
                }
            }
        }

        if ($needsFixing) {
            $request = RequestParser::parseAndDuplicateRequest($request);
        }

        return $request->query->all();
    }

    /**
     * Gets the Doctrine Type of a given property/resourceClass.
     *
     * @return null|string|Type
     */
    protected function getDoctrineFieldType(string $property, string $resourceClass)
    {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);

        return $metadata->getTypeOfField($propertyParts['field']);
    }

    /**
     * Is the property a mongo reference
     * if false, it's an embed relation.
     *
     * @param string $property
     * @param string $resourceClass
     * @return bool
     */
    protected function isReferenceProprety(string $property, string $resourceClass)
    {
        if ($this->isPropertyNested($property, $resourceClass)) {
            $propertyParts           = $this->splitPropertyParts($property, $resourceClass);
            $metadata                = $this->getClassMetadata($resourceClass);
            $associationPropertyName = $propertyParts['associations'][0];
            if (isset($metadata->fieldMappings[$associationPropertyName]['reference']) && $metadata->fieldMappings[$associationPropertyName]['reference'] === true) {
                return true;
            }
        }

        return false;
    }
}
