<?php

namespace Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Paginator\Decorator\PaginatorDecorator;
use App\Doctrine\Odm\Paginator\Decorator\Decorator\PaginatorPartialDecorator;
use Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Paginator\DoctrineMongoPaginator;
use Doctrine\ODM\MongoDB\Query\Builder;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class PaginationExtension
 * @package Sol\ApiPlatform\MongoDB\Birdge\Doctrine\Odm\Extension
 */
class PaginationExtension implements ContextAwareQueryResultCollectionExtensionInterface
{
    /**
     * @var ManagerRegistry Le manager registry de doctrine
     */
    private $managerRegistry;
    /**
     * @var RequestStack le stack de requete symfony
     *
     * @see https://symfony.com/blog/new-in-symfony-2-4-the-request-stack
     */
    private $requestStack;
    /**
     * @var ResourceMetadataFactoryInterface le factory de metadata d'api plateform
     */
    private $resourceMetadataFactory;
    /** @var bool configuration api-plateform, la pagination peut etre desactivé via les configurations */
    private $enabled;
    /** @var bool configuration api-plateform par default false, permet au client d'activer ou de desactiver la pagination */
    private $clientEnabled;
    /** @var bool configuration api-plateform par defaut false, permet au client de configurer le nombre d'item par page */
    private $clientItemsPerPage;
    /** @var int configuration api-plateform, le nombre d'item par page */
    private $itemsPerPage;
    /** @var string configuration api-plateform, le nom du parametre get/post contenant le nombre de page */
    private $pageParameterName;
    /** @var string configuration api-plateform, le nom du parametre get/post contenant l'activation ou nom de la pagination */
    private $enabledParameterName;
    /** @var string configuration api-plateform, le nom du parametre get/post contenant le nombre d'item par page */
    private $itemsPerPageParameterName;
    /** @var int configuration api-plateform, le nombre maximum d'item par page */
    private $maximumItemPerPage;
    /** @var bool configuration api-plateform, est ce que la pagination est partiel ? si oui pas de count de requête */
    private $partial;
    /** @var bool configuration api-plateform, est ce que la pagination est partiel pour le client ? si oui pas de count de requête */
    private $clientPartial;
    /** @var string configuration api-plateform, le nom du parametre get/post contenant l'activation ou non du mode partial */
    private $partialParameterName;

    /**
     * Construction du PaginatorExtension, ce paginator à des dépendances sur beaucoup de paramètre api platform.
     *
     * @param ManagerRegistry                  $managerRegistry
     * @param RequestStack                     $requestStack
     * @param ResourceMetadataFactoryInterface $resourceMetadataFactory
     * @param bool                             $enabled
     * @param bool                             $clientEnabled
     * @param bool                             $clientItemsPerPage
     * @param int                              $itemsPerPage
     * @param string                           $pageParameterName
     * @param string                           $enabledParameterName
     * @param string                           $itemsPerPageParameterName
     * @param null|int                         $maximumItemPerPage
     * @param bool                             $partial
     * @param bool                             $clientPartial
     * @param string                           $partialParameterName
     */
    public function __construct(ManagerRegistry $managerRegistry, RequestStack $requestStack, ResourceMetadataFactoryInterface $resourceMetadataFactory, bool $enabled = true, bool $clientEnabled = false, bool $clientItemsPerPage = false, int $itemsPerPage = 30, string $pageParameterName = 'page', string $enabledParameterName = 'pagination', string $itemsPerPageParameterName = 'itemsPerPage', int $maximumItemPerPage = null, bool $partial = false, bool $clientPartial = false, string $partialParameterName = 'partial')
    {
        $this->managerRegistry           = $managerRegistry;
        $this->requestStack              = $requestStack;
        $this->resourceMetadataFactory   = $resourceMetadataFactory;
        $this->enabled                   = $enabled;
        $this->clientEnabled             = $clientEnabled;
        $this->clientItemsPerPage        = $clientItemsPerPage;
        $this->itemsPerPage              = $itemsPerPage;
        $this->pageParameterName         = $pageParameterName;
        $this->enabledParameterName      = $enabledParameterName;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
        $this->maximumItemPerPage        = $maximumItemPerPage;
        $this->partial                   = $partial;
        $this->clientPartial             = $clientPartial;
        $this->partialParameterName      = $partialParameterName;
    }

    /**
     * @param Builder                     $queryBuilder
     * @param QueryNameGeneratorInterface $queryNameGenerator
     * @param string                      $resourceClass
     * @param null|string                 $operationName
     */
    public function applyToCollection(Builder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null): void
    {
        if (null === $resourceClass) {
            throw new InvalidArgumentException('The "$resourceClass" parameter must not be null');
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
        if (!$this->isPaginationEnabled($request, $resourceMetadata, $operationName)) { // la pagination est desactive, on quitte
            return;
        }

        // Recuperation du parametre $itemsPerPage, peut venir de la request ou de graphql
        $itemsPerPage = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_items_per_page', $this->itemsPerPage, true);
        if ($request->attributes->get('_graphql')) {
            $collectionArgs = $request->attributes->get('_graphql_collections_args', []);
            $itemsPerPage   = $collectionArgs[$resourceClass]['first'] ?? $itemsPerPage;
        }

        if ($resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_client_items_per_page', $this->clientItemsPerPage, true)) {
            $maxItemsPerPage = $resourceMetadata->getCollectionOperationAttribute($operationName, 'maximum_items_per_page', $this->maximumItemPerPage, true);

            $itemsPerPage = (int) $this->getPaginationParameter($request, $this->itemsPerPageParameterName, $itemsPerPage);
            $itemsPerPage = (null !== $maxItemsPerPage && $itemsPerPage >= $maxItemsPerPage ? $maxItemsPerPage : $itemsPerPage);
        }

        if (0 > $itemsPerPage) {
            throw new InvalidArgumentException('Item per page parameter should not be less than 0');
        }

        $page = $this->getPaginationParameter($request, $this->pageParameterName, 1);

        if (0 === $itemsPerPage && 1 < $page) {
            throw new InvalidArgumentException('Page should not be greater than 1 if itemsPegPage is equal to 0');
        }

        $firstResult = ($page - 1) * $itemsPerPage;
        if ($request->attributes->get('_graphql')) {
            $collectionArgs = $request->attributes->get('_graphql_collections_args', []);
            if (isset($collectionArgs[$resourceClass]['after'])) {
                $after       = \base64_decode($collectionArgs[$resourceClass]['after'], true);
                $firstResult = (int) $after;
                $firstResult = false === $after ? $firstResult : ++$firstResult;
            }
        }

        $queryBuilder
            ->limit($itemsPerPage)
            ->skip($firstResult)
        ;
    }

    /**
     * Est ce que la pagination est activé dans les configuration.
     *
     * @param Request          $request
     * @param ResourceMetadata $resourceMetadata
     * @param null|string      $operationName
     *
     * @return bool
     */
    private function isPaginationEnabled(Request $request, ResourceMetadata $resourceMetadata, string $operationName = null): bool
    {
        $enabled       = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_enabled', $this->enabled, true);
        $clientEnabled = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_client_enabled', $this->clientEnabled, true);

        if ($clientEnabled) {
            $enabled = filter_var($this->getPaginationParameter($request, $this->enabledParameterName, $enabled), FILTER_VALIDATE_BOOLEAN);
        }

        return $enabled;
    }

    /**
     * Recuperation des parametres de pagination d'API Plateform.
     *
     * @param Request $request
     * @param string  $parameterName
     * @param null    $default
     *
     * @return null|mixed
     */
    private function getPaginationParameter(Request $request, string $parameterName, $default = null)
    {
        if (null !== $paginationAttribute = $request->attributes->get('_api_pagination')) {
            return array_key_exists($parameterName, $paginationAttribute) ? $paginationAttribute[$parameterName] : $default;
        }

        return $request->query->get($parameterName, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsResult(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

        return $this->isPaginationEnabled($request, $resourceMetadata, $operationName);
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(Builder $queryBuilder, string $resourceClass = null, string $operationName = null, array $context = [])
    {
        $doctrineMongoPaginator = new DoctrineMongoPaginator($queryBuilder);

        $resourceMetadata = null === $resourceClass ? null : $this->resourceMetadataFactory->create($resourceClass);

        if ($this->isPartialPaginationEnabled($this->requestStack->getCurrentRequest(), $resourceMetadata, $operationName)) {
            return new PaginatorPartialDecorator($doctrineMongoPaginator);
        }

        return new PaginatorDecorator($doctrineMongoPaginator);
    }

    /**
     * Est ce que la pagination est partielle
     * -> Via les configuration
     * ->.
     *
     * @param null|Request          $request
     * @param null|ResourceMetadata $resourceMetadata
     * @param null|string           $operationName
     *
     * @return bool
     */
    private function isPartialPaginationEnabled(Request $request = null, ResourceMetadata $resourceMetadata = null, string $operationName = null): bool
    {
        $enabled       = $this->partial;
        $clientEnabled = $this->clientPartial;

        if ($resourceMetadata) {
            $enabled = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_partial', $enabled, true);

            if ($request) {
                $clientEnabled = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_client_partial', $clientEnabled, true);
            }
        }

        if ($clientEnabled && $request) {
            $enabled = filter_var($this->getPaginationParameter($request, $this->partialParameterName, $enabled), FILTER_VALIDATE_BOOLEAN);
        }

        return $enabled;
    }
}
