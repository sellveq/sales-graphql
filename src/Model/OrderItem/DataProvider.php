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

namespace ScandiPWA\SalesGraphQl\Model\OrderItem;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\SalesGraphQl\Model\OrderItem\DataProvider as SourceDataProvider;
use Magento\Tax\Helper\Data as TaxHelper;

class DataProvider extends SourceDataProvider
{
    /**
     * @var int[]
     */
    protected $orderItemIds = [];

    /**
     * @var array
     */
    protected $orderItemList = [];

    /**
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param ProductRepositoryInterface $productRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OptionsProcessor $optionsProcessor
     * @param TaxHelper|null $taxHelper Optional, mirroring the 2.4.9 parent: subclasses that
     *                                  predate this parameter still forward five arguments
     */
    public function __construct(
        protected readonly OrderItemRepositoryInterface $orderItemRepository,
        protected readonly ProductRepositoryInterface $productRepository,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        protected readonly OptionsProcessor $optionsProcessor,
        protected readonly ?TaxHelper $taxHelper = null
    ) {
        parent::__construct(
            $orderItemRepository,
            $productRepository,
            $orderRepository,
            $searchCriteriaBuilder,
            $optionsProcessor,
            $taxHelper
        );
    }

    /**
     * add an order item id to the list the next fetch() reads
     * @param int $orderItemId
     * @return void
     */
    public function addOrderItemId(int $orderItemId): void
    {
        if (!in_array($orderItemId, $this->orderItemIds)) {
            $this->orderItemList = [];
            $this->orderItemIds[] = $orderItemId;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderItemById(int $orderItemId): array
    {
        $orderItems = $this->fetch();

        if (!isset($orderItems[$orderItemId])) {
            return [];
        }

        return $orderItems[$orderItemId];
    }

    /**
     * fetch the buffered order items in the shape GraphQl consumes
     * @return array
     */
    protected function fetch()
    {
        if (empty($this->orderItemIds) || !empty($this->orderItemList)) {
            return $this->orderItemList;
        }

        $itemSearchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderItemInterface::ITEM_ID, $this->orderItemIds, 'in')
            ->create();

        $orderItems = $this->orderItemRepository->getList($itemSearchCriteria)->getItems();
        $productList = $this->fetchProducts($orderItems);
        $orderList = $this->fetchOrders($orderItems);

        foreach ($orderItems as $orderItem) {
            /** @var ProductInterface $associatedProduct */
            $associatedProduct = $productList[$orderItem->getProductId()] ?? null;
            /** @var OrderInterface $associatedOrder */
            $associatedOrder = $orderList[$orderItem->getOrderId()];
            $itemOptions = $this->optionsProcessor->getItemOptions($orderItem);
            $this->orderItemList[$orderItem->getItemId()] = [
                'id' => base64_encode((string)$orderItem->getItemId()),
                'associatedProduct' => $associatedProduct,
                'model' => $orderItem,
                'product_name' => $orderItem->getName(),
                'product_sku' => $orderItem->getSku(),
                'product_url_key' => $associatedProduct?->getUrlKey(),
                'product_type' => $orderItem->getProductType(),
                'parent_sku' => ($orderItem->getChildrenItems() && $associatedProduct) ?
                    $associatedProduct->getSku() : null,
                'status' => $orderItem->getStatus(),
                'discounts' => $this->getDiscountDetails($associatedOrder, $orderItem),
                'product_sale_price' => [
                    'value' => $this->taxHelper?->displaySalesPriceInclTax($associatedOrder->getStoreId())
                        ? $orderItem->getPriceInclTax()
                        : $orderItem->getPrice(),
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ],
                'selected_options' => $itemOptions['selected_options'],
                'entered_options' => $itemOptions['entered_options'],
                'quantity_ordered' => $orderItem->getQtyOrdered(),
                'quantity_shipped' => $orderItem->getQtyShipped(),
                'quantity_refunded' => $orderItem->getQtyRefunded(),
                'quantity_invoiced' => $orderItem->getQtyInvoiced(),
                'quantity_canceled' => $orderItem->getQtyCanceled(),
                'quantity_returned' => $orderItem->getQtyReturned(),
                'row_subtotal' => [
                    'value' => $orderItem->getRowTotal(),
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ]
            ];
        }

        return $this->orderItemList;
    }

    /**
     * @param array $orderItems
     * @return array
     */
    protected function fetchProducts(array $orderItems): array
    {
        $productIds = array_map(
            function ($orderItem) {
                return $orderItem->getProductId();
            },
            $orderItems
        );

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $productIds, 'in')
            ->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();
        $productList = [];

        foreach ($products as $product) {
            $productList[$product->getId()] = $product;
        }

        return $productList;
    }

    /**
     * @param array $orderItems
     * @return array
     */
    protected function fetchOrders(array $orderItems): array
    {
        $orderIds = array_map(
            function ($orderItem) {
                return $orderItem->getOrderId();
            },
            $orderItems
        );

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_id', $orderIds, 'in')
            ->create();
        $orders = $this->orderRepository->getList($searchCriteria)->getItems();

        $orderList = [];

        foreach ($orders as $order) {
            $orderList[$order->getEntityId()] = $order;
        }

        return $orderList;
    }

    /**
     * information about an applied discount
     * @param OrderInterface $associatedOrder
     * @param OrderItemInterface $orderItem
     * @return array
     */
    protected function getDiscountDetails(OrderInterface $associatedOrder, OrderItemInterface $orderItem): array
    {
        if (
            $associatedOrder->getDiscountDescription() === null
            && $orderItem->getDiscountAmount() == 0
            && $associatedOrder->getDiscountAmount() == 0
        ) {
            $discounts = [];
        } else {
            $discounts[] = [
                'label' => $associatedOrder->getDiscountDescription() ?? __('Discount'),
                'applied_to' => $this->getAppliedTo($associatedOrder),
                'amount' => [
                    'value' => abs((float)$orderItem->getDiscountAmount()),
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ]
            ];
        }

        return $discounts;
    }

    /**
     * whether the discount applies to the shipping or to the item
     * @param OrderInterface $order
     * @return string
     */
    protected function getAppliedTo(OrderInterface $order): string
    {
        if ((float)$order->getShippingDiscountAmount() > 0) {
            return self::APPLIED_TO_SHIPPING;
        }

        return self::APPLIED_TO_ITEM;
    }
}
