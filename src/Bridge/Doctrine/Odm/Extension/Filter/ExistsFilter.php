<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Class ExistsFilter
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension\Filter
 */
class ExistsFilter extends AbstractContextAwareFilter
{
    const QUERY_PARAMETER_KEY = 'exists';

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
            if (!$this->isPropertyMapped($property, $resourceClass, true) || !$this->isNullableField($property, $resourceClass)) {
                continue;
            }

            $description[sprintf('%s[%s]', $property, self::QUERY_PARAMETER_KEY)] = [
                'property' => $property,
                'type'     => 'bool',
                'required' => false,
            ];
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (
            !isset($value[self::QUERY_PARAMETER_KEY]) ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass, true) ||
            !$this->isNullableField($property, $resourceClass)
        ) {
            return;
        }

        if (\in_array($value[self::QUERY_PARAMETER_KEY], ['true', '1', '', null], true)) {
            $method = 'notEqual';
        } elseif (\in_array($value[self::QUERY_PARAMETER_KEY], ['false', '0'], true)) {
            $method = 'equals';
        } else {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid value for "%s[%s]", expected one of ( "%s" )', $property, self::QUERY_PARAMETER_KEY, implode('" | "', [
                    'true',
                    'false',
                    '1',
                    '0',
                ]))),
            ]);

            return;
        }

        $field = $property;

        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);

        if ($metadata->hasField($field)) {
            $queryBuilder->field($field)->{$method}(null);
        }
    }

    /**
     * Determines whether the given property refers to a nullable field.
     * @param string $property
     * @param string $resourceClass
     * @return bool
     */
    protected function isNullableField(string $property, string $resourceClass): bool
    {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);

        $field = $propertyParts['field'];

        if ($metadata instanceof ClassMetadata && $metadata->hasField($field)) {
            return $metadata->isNullable($field);
        }

        return false;
    }
}
