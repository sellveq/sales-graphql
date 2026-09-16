<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_SalesGraphQl
 * @copyright   Copyright 2020 Adobe. All Rights Reserved.
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\SalesGraphQl\Model\Resolver;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\InputException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\SalesGraphQl\Model\Formatter\Order as OrderFormatter;
use Magento\SalesGraphQl\Model\Resolver\CustomerOrders as CoreCustomerOrders;
use Magento\SalesGraphQl\Model\Resolver\CustomerOrders\Query\OrderFilter;
use Magento\SalesGraphQl\Model\Resolver\CustomerOrders\Query\OrderSort;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class CustomerOrders extends CoreCustomerOrders
{
    /**
     * @var StoreManagerInterface|mixed|null
     */
    protected $storeManager;

    /**
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderFilter $orderFilter
     * @param OrderFormatter $orderFormatter
     * @param OrderSort $orderSort
     * @param StoreManagerInterface|null $storeManager
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderFilter $orderFilter,
        private readonly OrderFormatter $orderFormatter,
        private readonly OrderSort $orderSort,
        ?StoreManagerInterface $storeManager = null
    ) {
        parent::__construct(
            $orderRepository,
            $searchCriteriaBuilder,
            $orderFilter,
            $orderFormatter,
            $orderSort,
            $storeManager
        );

        $this->storeManager = $storeManager ?? ObjectManager::getInstance()->get(StoreManagerInterface::class);
    }

    /**
     * {@inheritdoc}
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        /** @var ContextInterface $context */
        if ($context->getExtensionAttributes()->getIsCustomer() === false) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        if ($args['currentPage'] < 1) {
            throw new GraphQlInputException(__('currentPage value must be greater than 0.'));
        }

        if ($args['pageSize'] < 1) {
            throw new GraphQlInputException(__('pageSize value must be greater than 0.'));
        }

        $storeIds = [];
        $userId = $context->getUserId();
        /** @var StoreInterface $store */
        $store = $context->getExtensionAttributes()->getStore();

        if (isset($args['scope'])) {
            $storeIds = $this->getStoresByScope($args['scope'], $store);
        }

        try {
            $searchResult = $this->getSearchResult($args, (int)$userId, (int)$store->getId(), $storeIds);
            $maxPages = (int)ceil($searchResult->getTotalCount() / $searchResult->getPageSize());
        } catch (InputException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }

        $ordersArray = [];

        foreach ($searchResult->getItems() as $orderModel) {
            $ordersArray[] = $this->orderFormatter->format($orderModel);
        }

        return [
            'total_count' => $searchResult->getTotalCount(),
            'items' => $ordersArray,
            'page_info' => [
                'page_size' => $searchResult->getPageSize(),
                'current_page' => $searchResult->getCurPage(),
                'total_pages' => $maxPages,
            ],
            'store_ids' => $storeIds,
        ];
    }

    /**
     * search result built from the graphql query arguments
     * @param array $args
     * @param int $userId
     * @param int $storeId
     * @param array $storeIds
     * @return OrderSearchResultInterface
     * @throws InputException
     */
    private function getSearchResult(array $args, int $userId, int $storeId, array $storeIds)
    {
        $filterGroups = $this->orderFilter->createFilterGroups($args, $userId, (int)$storeId, $storeIds);
        $this->searchCriteriaBuilder->setFilterGroups($filterGroups);

        if (isset($args['currentPage'])) {
            $this->searchCriteriaBuilder->setCurrentPage($args['currentPage']);
        }

        if (isset($args['pageSize'])) {
            $this->searchCriteriaBuilder->setPageSize($args['pageSize']);
        }

        if (isset($args['sort'])) {
            $sortOrders = $this->orderSort->createSortOrders($args);
            $this->searchCriteriaBuilder->setSortOrders($sortOrders);
        }

        return $this->orderRepository->getList($this->searchCriteriaBuilder->create());
    }

    /**
     * the store ids a scope makes eligible to filter by
     * @param string $scope
     * @param StoreInterface $store
     * @return array
     */
    private function getStoresByScope(string $scope, StoreInterface $store): array
    {
        $storeIds = [];
        switch ($scope) {
            case 'GLOBAL':
                $storeIds = $this->getStoresByFilter(null, null);
                break;
            case 'WEBSITE':
                $websiteId = $store->getWebsiteId();
                $storeIds = $this->getStoresByFilter((int)$websiteId, null);
                break;
            case 'STORE':
                $storeGroupId = $store->getStoreGroupId();
                $storeIds = $this->getStoresByFilter(null, (int)$storeGroupId);
                break;
            default:
                break;
        }
        return $storeIds;
    }

    /**
     * store ids filtered by website or store group
     * @param int|null $websiteId
     * @param int|null $storeGroupId
     * @return array
     */
    private function getStoresByFilter(?int $websiteId, ?int $storeGroupId): array
    {
        $stores = $this->storeManager->getStores(true, true);
        $storeIds = [];
        foreach ($stores as $store) {
            if (
                isset($websiteId) && $websiteId === (int)$store->getWebsiteId()
                ||
                isset($storeGroupId) && $storeGroupId === (int)$store->getStoreGroupId()
            ) {
                $storeIds[] = $store->getId();
            } elseif (!isset($websiteId) && !isset($storeGroupId)) {
                $storeIds[] = $store->getId();
            }
        }
        return $storeIds;
    }
}
