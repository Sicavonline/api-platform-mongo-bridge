<?php

namespace Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension\Filter;

use ApiPlatform\Core\Api\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\ODM\MongoDB\Types\Type;

/**
 * Class BooleanFilter
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension\Filter
 */
class BooleanFilter extends AbstractContextAwareFilter implements FilterInterface
{
    /**
     * @inheritdoc
     */
    protected function filterProperty(string $property, $value, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass) ||
            !$this->isBooleanField($property, $resourceClass)
        ) {
            return;
        }

        if (\in_array($value, [true, 'true', '1'], true)) {
            $value = true;
        } elseif (\in_array($value, [false, 'false', '0'], true)) {
            $value = false;
        } else {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid boolean value for "%s" property, expected one of ( "%s" )', $property, implode('" | "', [
                    'true',
                    'false',
                    '1',
                    '0',
                ]))),
            ]);

            return;
        }

        $field = $property;
        if ($this->isReferenceProprety($property, $resourceClass)) { // pas de filtre sur les refereneces
            throw new \Exception('Les references ne sont pas supportes en tant que filtre, implementer le $in si besoin');
        } else { // si embed ou property c'est ok
            $queryBuilder->field($field)->equals($value);
        }
    }

    /**
     * @inheritdoc
     */
    public function getDescription(string $resourceClass): array
    {
        $description = [];

        $properties = $this->properties;
        if (null === $properties) {
            $properties = array_fill_keys($this->getClassMetadata($resourceClass)->getFieldNames(), null);
        }

        foreach ($properties as $property => $unused) {
            if (!$this->isPropertyMapped($property, $resourceClass) || !$this->isBooleanField($property, $resourceClass)) {
                continue;
            }

            $description[$property] = [
                'property' => $property,
                'type'     => 'bool',
                'required' => false,
            ];
        }

        return $description;
    }

    /**
     * Determines whether the given property refers to a boolean field.
     *
     * @param string $property
     * @param string $resourceClass
     *
     * @return bool
     */
    protected function isBooleanField(string $property, string $resourceClass): bool
    {
        $propertyParts = $this->splitPropertyParts($property, $resourceClass);
        $metadata      = $this->getNestedMetadata($resourceClass, $propertyParts['associations']);

        return Type::BOOLEAN === $metadata->getTypeOfField($propertyParts['field']);
    }
}
