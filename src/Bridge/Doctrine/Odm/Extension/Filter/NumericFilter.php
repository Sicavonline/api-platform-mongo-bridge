<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Types\Type;

/**
 * Class NumericFilter
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter
 */
class NumericFilter extends AbstractContextAwareFilter
{
    /**
     * Type of numeric in Doctrine.
     *
     * @see http://doctrine-orm.readthedocs.org/projects/doctrine-dbal/en/latest/reference/types.html
     */
    const DOCTRINE_NUMERIC_TYPES = [
        Type::INT     => true,
        Type::INTEGER => true,
        Type::FLOAT   => true,
    ];

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

        foreach ($properties as $property => $unused) {
            if (!$this->isPropertyMapped($property, $resourceClass) || !$this->isNumericField($property, $resourceClass)) {
                continue;
            }

            $description[$property] = [
                'property' => $property,
                'type'     => $this->getType($this->getDoctrineFieldType($property, $resourceClass)),
                'required' => false,
            ];
        }

        return $description;
    }

    /**
     * Gets the PHP type corresponding to this Doctrine type.
     *
     * @param string $doctrineType
     *
     * @return string
     */
    private function getType(string $doctrineType = null): string
    {
        if (null === $doctrineType || Type::FLOAT === $doctrineType) {
            return 'string';
        }

        if (Type::FLOAT === $doctrineType) {
            return 'float';
        }

        return 'int';
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        if (!is_numeric($value)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid numeric value for "%s::%s" property', $resourceClass, $property)),
            ]);

            return;
        }

        $field = $property;
        if (!isset($this::DOCTRINE_NUMERIC_TYPES[$this->getDoctrineFieldType($property, $resourceClass)])) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('The field "%s" of class "%s" is not a doctrine numeric type.', $field, $resourceClass)),
            ]);

            return;
        }

        switch ($this->getDoctrineFieldType($property, $resourceClass)) {
            case Type::INT:
            case Type::INTEGER:
                $value = (int) $value;
                break;
            case Type::FLOAT:
                $value = (float) $value;
                break;
        }

        $queryBuilder->field($property)->equals($value);
    }

    /**
     * Determines whether the given property refers to a numeric field.
     * @param string $property
     * @param string $resourceClass
     * @return bool
     */
    protected function isNumericField(string $property, string $resourceClass): bool
    {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);

        return isset($this::DOCTRINE_NUMERIC_TYPES[$metadata->getTypeOfField($propertyParts['field'])]);
    }
}
