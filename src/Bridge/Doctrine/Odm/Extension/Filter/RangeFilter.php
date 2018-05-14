<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter;


use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Doctrine\ODM\MongoDB\Query\Builder;

/**
 * Class RangeFilter
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension\Filter
 */
class RangeFilter extends AbstractContextAwareFilter
{
    const PARAMETER_BETWEEN = 'between';
    const PARAMETER_GREATER_THAN = 'gt';
    const PARAMETER_GREATER_THAN_OR_EQUAL = 'gte';
    const PARAMETER_LESS_THAN = 'lt';
    const PARAMETER_LESS_THAN_OR_EQUAL = 'lte';

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
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $description += $this->getFilterDescription($property, self::PARAMETER_BETWEEN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN_OR_EQUAL);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN_OR_EQUAL);
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $values, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (
            !\is_array($values) ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        $field = $property;

        foreach ($values as $operator => $value) {
            $this->addWhere(
                $queryBuilder,
                $field,
                $operator,
                $value
            );
        }
    }

    /**
     * @param Builder $queryBuilder
     * @param $field
     * @param $operator
     * @param $value
     */
    protected function addWhere(Builder $queryBuilder, $field, $operator, $value)
    {
        $queryBuilder->field($field);

        switch ($operator) {
            case self::PARAMETER_BETWEEN:
                $rangeValue = explode('..', $value);

                if (2 !== \count($rangeValue)) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid format for "[%s]", expected "<min>..<max>"', $operator)),
                    ]);

                    return;
                }

                if (!is_numeric($rangeValue[0]) || !is_numeric($rangeValue[1])) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid values for "[%s]" range, expected numbers', $operator)),
                    ]);

                    return;
                }

                $queryBuilder
                    ->gte((float)$rangeValue[0])
                    ->lte((float)$rangeValue[1]);

                break;
            case self::PARAMETER_GREATER_THAN:
                if (!is_numeric($value)) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
                    ]);

                    return;
                }

                $queryBuilder
                    ->gt((float)$value);

                break;
            case self::PARAMETER_GREATER_THAN_OR_EQUAL:
                if (!is_numeric($value)) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
                    ]);

                    return;
                }

                $queryBuilder
                    ->gte((float)$value);

                break;
            case self::PARAMETER_LESS_THAN:
                if (!is_numeric($value)) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
                    ]);

                    return;
                }

                $queryBuilder
                    ->lt((float)$value);

                break;
            case self::PARAMETER_LESS_THAN_OR_EQUAL:
                if (!is_numeric($value)) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
                    ]);

                    return;
                }

                $queryBuilder
                    ->lte((float)$value);

                break;
        }
    }

    /**
     * Gets filter description.
     *
     * @param string $fieldName
     * @param string $operator
     *
     * @return array
     */
    protected function getFilterDescription(string $fieldName, string $operator): array
    {
        return [
            sprintf('%s[%s]', $fieldName, $operator) => [
                'property' => $fieldName,
                'type' => 'string',
                'required' => false,
            ],
        ];
    }
}