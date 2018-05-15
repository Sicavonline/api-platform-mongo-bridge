<?php

namespace Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension\Filter;


use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Types\Type;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Doctrine\Bundle\MongoDBBundle\Logger\LoggerInterface;

/**
 * Class SearchFilter
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension\Filter
 */
class SearchFilter extends AbstractContextAwareFilter
{
    /**
     * @var string Exact matching
     */
    const STRATEGY_EXACT = 'exact';

    /**
     * @var string The value must be contained in the field
     */
    const STRATEGY_PARTIAL = 'partial';

    /**
     * @var string Finds fields that are starting with the value
     */
    const STRATEGY_START = 'start';

    /**
     * @var string Finds fields that are ending with the value
     */
    const STRATEGY_END = 'end';

    /**
     * @var string Finds fields that are starting with the word
     */
    const STRATEGY_WORD_START = 'word_start';

    protected $iriConverter;
    protected $propertyAccessor;

    /**
     * SearchFilter constructor.
     * @param ManagerRegistry $managerRegistry
     * @param null|RequestStack $requestStack
     * @param IriConverterInterface $iriConverter
     * @param PropertyAccessorInterface|null $propertyAccessor
     * @param LoggerInterface|null $logger
     * @param array|null $properties
     */
    public function __construct(ManagerRegistry $managerRegistry, $requestStack = null, IriConverterInterface $iriConverter, PropertyAccessorInterface $propertyAccessor = null, LoggerInterface $logger = null, array $properties = null)
    {
        parent::__construct($managerRegistry, $requestStack, $logger, $properties);

        $this->iriConverter = $iriConverter;
        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->properties;
        if (null === $properties) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $strategy) {
            if (!$this->isPropertyMapped($property, $resourceClass, true)) {
                continue;
            }

            if ($this->isPropertyNested($property, $resourceClass)) {
                $propertyParts = $this->splitPropertyParts($property, $resourceClass);
                $field = $propertyParts['field'];
                $metadata = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);
            } else {
                $field = $property;
                $metadata = $this->getClassMetadata($resourceClass);
            }

            if ($metadata->hasField($field)) {
                $typeOfField = $this->getType($metadata->getTypeOfField($field));
                $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;
                $filterParameterNames = [$property];

                if (self::STRATEGY_EXACT === $strategy) {
                    $filterParameterNames[] = $property.'[]';
                }

                foreach ($filterParameterNames as $filterParameterName) {
                    $description[$filterParameterName] = [
                        'property' => $property,
                        'type' => $typeOfField,
                        'required' => false,
                        'strategy' => $strategy,
                    ];
                }
            } elseif ($metadata->hasAssociation($field)) {
                $filterParameterNames = [
                    $property,
                    $property.'[]',
                ];

                foreach ($filterParameterNames as $filterParameterName) {
                    $description[$filterParameterName] = [
                        'property' => $property,
                        'type' => 'string',
                        'required' => false,
                        'strategy' => self::STRATEGY_EXACT,
                    ];
                }
            }
        }

        return $description;
    }

    /**
     * Converts a Doctrine type in PHP type.
     *
     * @param string $doctrineType
     *
     * @return string
     */
    private function getType(string $doctrineType): string
    {
        switch ($doctrineType) {
            case Type::INTEGER:
                return 'int';
            case Type::BOOLEAN:
                return 'bool';
            case Type::DATE:
                return \DateTimeInterface::class;
            case Type::FLOAT:
                return 'float';
        }

        return 'string';
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (
            null === $value ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass, true)
        ) {
            return;
        }

        $field = $property;

        $metadata = $this->getClassMetadata($resourceClass);

        $values = $this->normalizeValues((array) $value);

        if (empty($values)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one value is required, multiple values should be in "%1$s[]=firstvalue&%1$s[]=secondvalue" format', $property)),
            ]);

            return;
        }

        $caseSensitive = true;

        if ($metadata->hasField($field)) {
            if ('id' === $field) {
                $values = array_map([$this, 'getIdFromValue'], $values);
            }

            if (!$this->hasValidValues($values, $this->getDoctrineFieldType($property, $resourceClass))) {
                $this->logger->notice('Invalid filter ignored', [
                    'exception' => new InvalidArgumentException(sprintf('Values for field "%s" are not valid according to the doctrine type.', $field)),
                ]);

                return;
            }

            $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;

            // prefixing the strategy with i makes it case insensitive
            if (0 === strpos($strategy, 'i')) {
                $strategy = substr($strategy, 1);
                $caseSensitive = false;
            }

            if (1 === \count($values)) {
                $this->addWhereByStrategy($strategy, $queryBuilder, $field, $values[0], $caseSensitive);

                return;
            }

            if (self::STRATEGY_EXACT !== $strategy) { // peut etre supprime est remplacer par une boucle sur les elements
                $this->logger->notice('Invalid filter ignored', [
                    'exception' => new InvalidArgumentException(sprintf('"%s" strategy selected for "%s" property, but only "%s" strategy supports multiple values', $strategy, $property, self::STRATEGY_EXACT)),
                ]);

                return;
            }

            if($caseSensitive === false) {
                foreach ($values as $value) {
                    $this->addWhereByStrategy(self::STRATEGY_EXACT, $queryBuilder, $field, $value, false);
                }
            } else  {
                $queryBuilder
                    ->field($field)
                    ->in($values);
            }
        }
    }

    /**
     * @param string $strategy
     * @param Builder $queryBuilder
     * @param string $field
     * @param $value
     * @param bool $caseSensitive
     */
    protected function addWhereByStrategy(string $strategy, Builder $queryBuilder, string $field, $value, bool $caseSensitive)
    {
        $wrapCase = $this->createWrapCase($caseSensitive);

        switch ($strategy) {
            case null:
            case self::STRATEGY_EXACT:
                $queryBuilder
                    ->field($field)->equals(new \MongoRegex($wrapCase("^" . $value . "$")));
                break;
            case self::STRATEGY_PARTIAL:
                $queryBuilder
                    ->field($field)->equals(new \MongoRegex($wrapCase($value)));
                break;
            case self::STRATEGY_START:
                $queryBuilder
                    ->field($field)->equals(new \MongoRegex($wrapCase("^" . $value)));
                break;
            case self::STRATEGY_END:
                $queryBuilder
                    ->field($field)->equals(new \MongoRegex($wrapCase($value . "$")));
                break;
            case self::STRATEGY_WORD_START:
                $queryBuilder
                    ->field($field)->equals(new \MongoRegex($wrapCase("^" . $value)));
                break;
            default:
                throw new InvalidArgumentException(sprintf('strategy %s does not exist.', $strategy));
        }
    }

    /**
     * Creates a function that will wrap a Doctrine expression according to the
     * specified case sensitivity.
     *
     * For example, "o.name" will get wrapped into "LOWER(o.name)" when $caseSensitive
     * is false.
     *
     * @param bool $caseSensitive
     *
     * @return \Closure
     */
    protected function createWrapCase(bool $caseSensitive): \Closure
    {
        return function (string $expr) use ($caseSensitive): string {
            if ($caseSensitive) {
                return sprintf('/%s/', $expr);
            }

            return sprintf('/%s/i', $expr);
        };
    }

    /**
     * Gets the ID from an IRI or a raw ID.
     *
     * @param string $value
     *
     * @return mixed
     */
    protected function getIdFromValue(string $value)
    {
        try {
            if ($item = $this->iriConverter->getItemFromIri($value, ['fetch_data' => false])) {
                return $this->propertyAccessor->getValue($item, 'id');
            }
        } catch (InvalidArgumentException $e) {
            // Do nothing, return the raw value
        }

        return $value;
    }

    /**
     * Normalize the values array.
     *
     * @param array $values
     *
     * @return array
     */
    protected function normalizeValues(array $values): array
    {
        foreach ($values as $key => $value) {
            if (!\is_int($key) || !\is_string($value)) {
                unset($values[$key]);
            }
        }

        return array_values($values);
    }

    /**
     * When the field should be an integer, check that the given value is a valid one.
     * @param array $values
     * @param null $type
     * @return bool
     */
    protected function hasValidValues(array $values, $type = null): bool
    {
        foreach ($values as $key => $value) {
            if (Type::INTEGER === $type && null !== $value && false === filter_var($value, FILTER_VALIDATE_INT)) {
                return false;
            }
        }

        return true;
    }
}