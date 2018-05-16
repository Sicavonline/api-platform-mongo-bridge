<?php
/**
 * Created by PhpStorm.
 * User: clotail
 * Date: 04/05/2018
 * Time: 09:37
 */

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\DataProvider;


use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultItemExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\RuntimeException;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Item data provider for the Doctrine ODM.
 * Class ItemDataProvider
 * @package Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\DataProvider
 */
class ItemDataProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;
    /**
     * @var array
     */
    private $collectionExtensions;

    /**
     * ItemDataProvider constructor.
     * @param ManagerRegistry $managerRegistry
     * @param array $itemExtensions
     */
    public function __construct(ManagerRegistry $managerRegistry, array $itemExtensions)
    {
        $this->managerRegistry = $managerRegistry;
        $this->itemExtensions    = $itemExtensions['$itemExtensions'];
    }

    /**
     * @param string $resourceClass
     * @param string|null $operationName
     * @param array $context
     * @return bool
     */
    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return null !== $this->managerRegistry->getManagerForClass($resourceClass);
    }


    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = [])
    {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);
        $identifiers = ['id' => $id];

        $fetchData = $context['fetch_data'] ?? true;
        if (!$fetchData && $manager instanceof EntityManagerInterface) {
            return $manager->getReference($resourceClass, $identifiers);
        }

        $repository = $manager->getRepository($resourceClass);
        if (!method_exists($repository, 'createQueryBuilder')) {
            throw new RuntimeException('The repository class must have a "createQueryBuilder" method.');
        }

        $queryNameGenerator = new QueryNameGenerator();
        $queryBuilder       = $repository->createQueryBuilder('p');

        /** @var ContextAwareQueryI $extension */
        foreach ($this->itemExtensions as $extension) {
            $extension->applyToItem($queryBuilder, $queryNameGenerator, $resourceClass, $identifiers, $operationName, $context);
            if ($extension instanceof QueryResultItemExtensionInterface && $extension->supportsResult($resourceClass, $operationName, $context)) { // attention au priorite
                return $extension->getSingleResult($queryBuilder, $resourceClass, $operationName, $context);
            }
        }

        return $queryBuilder->getQuery()->getSingleResult();
    }
}