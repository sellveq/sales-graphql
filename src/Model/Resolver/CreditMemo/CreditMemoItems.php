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

namespace ScandiPWA\SalesGraphQl\Model\Resolver\CreditMemo;

use Closure;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\CreditmemoItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\SalesGraphQl\Model\OrderItem\DataProvider as OrderItemProvider;
use Magento\SalesGraphQl\Model\Resolver\CreditMemo\CreditMemoItems as SourceCreditMemoItems;

class CreditMemoItems extends SourceCreditMemoItems
{
    /**
     * @param ValueFactory $valueFactory
     * @param OrderItemProvider $orderItemProvider
     */
    public function __construct(
        private readonly ValueFactory $valueFactory,
        private readonly OrderItemProvider $orderItemProvider
    ) {
        parent::__construct(
            $valueFactory,
            $orderItemProvider
        );
    }

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!(($value['model'] ?? null) instanceof CreditmemoInterface)) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        if (!(($value['order'] ?? null) instanceof OrderInterface)) {
            throw new LocalizedException(__('"order" value should be specified'));
        }

        /** @var CreditmemoInterface $creditMemoModel */
        $creditMemoModel = $value['model'];
        /** @var OrderInterface $parentOrderModel */
        $parentOrderModel = $value['order'];

        return $this->valueFactory->create(
            $this->getCreditMemoItems($parentOrderModel, $creditMemoModel->getItems())
        );
    }

    /**
     * credit memo items as a promise the value factory resolves
     * @param OrderInterface $order
     * @param array $creditMemoItems
     * @return Closure
     */
    protected function getCreditMemoItems(OrderInterface $order, array $creditMemoItems): Closure
    {
        $orderItems = [];

        foreach ($creditMemoItems as $item) {
            $this->orderItemProvider->addOrderItemId((int)$item->getOrderItemId());
        }

        return function () use ($order, $creditMemoItems, $orderItems): array {
            foreach ($creditMemoItems as $creditMemoItem) {
                $orderItem = $this->orderItemProvider->getOrderItemById((int)$creditMemoItem->getOrderItemId());
                /** @var OrderItemInterface $orderItemModel */
                $orderItemModel = $orderItem['model'];

                if (!$orderItemModel->getParentItem()) {
                    $creditMemoItemData = $this->getCreditMemoItemData($order, $creditMemoItem);

                    if (!empty($creditMemoItemData)) {
                        $orderItems[$creditMemoItem->getOrderItemId()] = $creditMemoItemData;
                    }
                }
            }

            return $orderItems;
        };
    }

    /**
     * @param OrderInterface $order
     * @param CreditmemoItemInterface $creditMemoItem
     * @return array
     */
    protected function getCreditMemoItemData(OrderInterface $order, CreditmemoItemInterface $creditMemoItem): array
    {
        $orderItem = $this->orderItemProvider->getOrderItemById((int)$creditMemoItem->getOrderItemId());

        return [
            'id' => base64_encode((string)$creditMemoItem->getEntityId()),
            'product_name' => $creditMemoItem->getName(),
            'product_sku' => $creditMemoItem->getSku(),
            'product_sale_price' => [
                'value' => $creditMemoItem->getPrice(),
                'currency' => $order->getOrderCurrencyCode()
            ],
            'quantity_refunded' => $creditMemoItem->getQty(),
            'model' => $creditMemoItem,
            'product_type' => $orderItem['product_type'],
            'discounts' => $this->formatDiscountDetails($order, $creditMemoItem),
            'row_subtotal' => [
                'value' => $creditMemoItem->getRowTotal(),
                'currency' => $order->getOrderCurrencyCode()
            ]
        ];
    }

    /**
     * formatted information about an applied discount
     * @param OrderInterface $associatedOrder
     * @param CreditmemoItemInterface $creditmemoItem
     * @return array
     */
    protected function formatDiscountDetails(
        OrderInterface $associatedOrder,
        CreditmemoItemInterface $creditmemoItem
    ): array {
        if ($associatedOrder->getDiscountDescription() === null
            && $creditmemoItem->getDiscountAmount() == 0
            && $associatedOrder->getDiscountAmount() == 0
        ) {
            $discounts = [];
        } else {
            $discounts[] = [
                'label' => $associatedOrder->getDiscountDescription() ?? __('Discount'),
                'amount' => [
                    'value' => abs((float)$creditmemoItem->getDiscountAmount()),
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ]
            ];
        }

        return $discounts;
    }
}
