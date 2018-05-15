<?php

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension\Filter;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\ODM\MongoDB\Query\Builder;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\Bundle\MongoDBBundle\Logger\LoggerInterface;

/**
 * Class OrderFilter
 * @package Sol\ApiPlatform\MongoBridge\Birdge\Doctrine\Odm\Extension\Filter
 */
class OrderFilter extends AbstractContextAwareFilter
{
    const NULLS_SMALLEST = 'nulls_smallest';
    const NULLS_LARGEST = 'nulls_largest';
    const NULLS_DIRECTION_MAP = [
        self::NULLS_SMALLEST => [
            'ASC' => 'ASC',
            'DESC' => 'DESC',
        ],
        self::NULLS_LARGEST => [
            'ASC' => 'DESC',
            'DESC' => 'ASC',
        ],
    ];

    /**
     * @var string Keyword used to retrieve the value
     */
    protected $orderParameterName;

    /**
     * OrderFilter constructor.
     * @param ManagerRegistry $managerRegistry
     * @param null|RequestStack $requestStack
     * @param string $orderParameterName
     * @param LoggerInterface|null $logger
     * @param array|null $properties
     */
    public function __construct(ManagerRegistry $managerRegistry, $requestStack = null, string $orderParameterName = 'order', LoggerInterface $logger = null, array $properties = null)
    {
        if (null !== $properties) {
            $properties = array_map(function ($propertyOptions) {
                // shorthand for default direction
                if (\is_string($propertyOptions)) {
                    $propertyOptions = [
                        'default_direction' => $propertyOptions,
                    ];
                }

                return $propertyOptions;
            }, $properties);
        }

        parent::__construct($managerRegistry, $requestStack, $logger, $properties);

        $this->orderParameterName = $orderParameterName;

    }

    /**
     * {@inheritdoc}
     */
    public function apply(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []) : void
    {

        if (!isset($context['filters'][$this->orderParameterName]) || !\is_array($context['filters'][$this->orderParameterName])) {
            $context['filters'] = null;
            parent::apply($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);

            return;
        }

        foreach ($context['filters'][$this->orderParameterName] as $property => $value) {
            $this->filterProperty($property, $value, $queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
        }
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

        foreach ($properties as $property => $propertyOptions) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $description[sprintf('%s[%s]', $this->orderParameterName, $property)] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $direction, Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = [])
    {
        if (!$this->isPropertyEnabled($property, $resourceClass) || !$this->isPropertyMapped($property, $resourceClass)) {
            return;
        }

        if (empty($direction) && null !== $defaultDirection = $this->properties[$property]['default_direction'] ?? null) {
            // fallback to default direction
            $direction = $defaultDirection;
        }

        $direction = strtoupper($direction);
        if (!\in_array($direction, ['ASC', 'DESC'], true)) {
            return;
        }

        $field = $property;

        if ($this->isReferenceProprety($property, $resourceClass)) {
            throw new \Exception('Les references ne sont pas supportes en tant que filtre, implementer le $in si besoin');
        }

        $queryBuilder->sort($field, $direction);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractProperties(Request $request/*, string $resourceClass*/): array
    {
        @trigger_error(sprintf('The use of "%s::extractProperties()" is deprecated since 2.2. Use the "filters" key of the context instead.', __CLASS__), E_USER_DEPRECATED);
        $properties = $request->query->get($this->orderParameterName);

        return \is_array($properties) ? $properties : [];
    }
}