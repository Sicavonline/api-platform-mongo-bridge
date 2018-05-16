<?php
/**
 * Created by PhpStorm.
 * User: clotail
 * Date: 04/05/2018
 * Time: 09:37
 */

namespace Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\DataProvider;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\DataProvider\ContextAwareCollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use ApiPlatform\Core\Exception\RuntimeException;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension\ContextAwareQueryCollectionExtensionInterface;
use Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension\QueryCollectionExtensionInterface;
use Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\Extension\QueryResultCollectionExtensionInterface;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ODM\MongoDB\DocumentRepository;

/**
 * Collection data provider for the Doctrine ODM.
 *
 * Class CollectionDataProvider
 * @package Sol\ApiPlatform\MongoBridge\Bridge\Doctrine\Odm\DataProvider
 */
class CollectionDataProvider implements ContextAwareCollectionDataProviderInterface, RestrictedDataProviderInterface
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
     * CollectionDataProvider constructor.
     * @param ManagerRegistry $managerRegistry the mongodb registry manager
     * @param array $collectionExtensions all extension for mongodb
     */
    public function __construct(ManagerRegistry $managerRegistry, array $collectionExtensions = [])
    {
        $this->managerRegistry = $managerRegistry;
        $this->collectionExtensions = $collectionExtensions['$collectionExtensions'];
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return null !== $this->managerRegistry->getManagerForClass($resourceClass);
    }

    /**ou
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function getCollection(string $resourceClass, string $operationName = null, array $context = [])
    {
        $manager = $this->managerRegistry->getManagerForClass($resourceClass);

        /** @var DocumentRepository $repository */
        $repository = $manager->getRepository($resourceClass);
        if (!method_exists($repository, 'createQueryBuilder')) {
            throw new RuntimeException('The repository class must have a "createQueryBuilder" method.');
        }

        $queryBuilder = $repository->createQueryBuilder();
        // keep it for extension compatibility but no use for now
        $queryNameGenerator = new QueryNameGenerator();

        /** @var ContextAwareQueryCollectionExtensionInterface $extension */
        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection($queryBuilder, $queryNameGenerator, $resourceClass, $operationName, $context);
            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operationName)) {
                return $extension->getResult($queryBuilder)->toArray();
            }
        }

        return $queryBuilder->getQuery()->execute();
    }
}