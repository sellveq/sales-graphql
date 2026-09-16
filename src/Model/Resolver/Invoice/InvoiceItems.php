<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_SalesGraphQl
 * @copyright   Copyright © Magento, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ScandiPWA\SalesGraphQl\Model\Resolver\Invoice;

use Closure;
use Magento\Framework\GraphQl\Query\Resolver\ValueFactory;
use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\SalesGraphQl\Model\OrderItem\DataProvider as OrderItemProvider;
use Magento\SalesGraphQl\Model\Resolver\Invoice\InvoiceItems as SourceInvoiceItems;

class InvoiceItems extends SourceInvoiceItems
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
     */
    public function getInvoiceItems(OrderInterface $order, array $invoiceItems): Closure
    {
        $itemsList = [];

        foreach ($invoiceItems as $Item) {
            $this->orderItemProvider->addOrderItemId((int)$Item->getOrderItemId());
        }

        return function () use ($order, $invoiceItems, $itemsList): array {
            foreach ($invoiceItems as $invoiceItem) {
                $orderItem = $this->orderItemProvider->getOrderItemById((int)$invoiceItem->getOrderItemId());
                /** @var OrderItemInterface $orderItemModel */
                $orderItemModel = $orderItem['model'];

                if (!$orderItemModel->getParentItem()) {
                    $invoiceItemData = $this->getInvoiceItemData($order, $invoiceItem);

                    if (!empty($invoiceItemData)) {
                        $itemsList[$invoiceItem->getOrderItemId()] = $invoiceItemData;
                    }
                }
            }

            return $itemsList;
        };
    }

    /**
     * @param OrderInterface $order
     * @param InvoiceItemInterface $invoiceItem
     * @return array
     */
    protected function getInvoiceItemData(OrderInterface $order, InvoiceItemInterface $invoiceItem): array
    {
        $orderItem = $this->orderItemProvider->getOrderItemById((int)$invoiceItem->getOrderItemId());

        return [
            'id' => base64_encode((string)$invoiceItem->getEntityId()),
            'product_name' => $invoiceItem->getName(),
            'product_sku' => $invoiceItem->getSku(),
            'product_sale_price' => [
                'value' => $invoiceItem->getPrice(),
                'currency' => $order->getOrderCurrencyCode()
            ],
            'quantity_invoiced' => $invoiceItem->getQty(),
            'model' => $invoiceItem,
            'product_type' => $orderItem['product_type'],
            'order_item' => $orderItem,
            'discounts' => $this->formatDiscountDetails($order, $invoiceItem),
            'row_subtotal' => [
                'value' => $invoiceItem->getRowTotal(),
                'currency' => $order->getOrderCurrencyCode()
            ]
        ];
    }

    /**
     * formatted information about an applied discount
     * @param OrderInterface $associatedOrder
     * @param InvoiceItemInterface $invoiceItem
     * @return array
     */
    protected function formatDiscountDetails(OrderInterface $associatedOrder, InvoiceItemInterface $invoiceItem): array
    {
        if ($associatedOrder->getDiscountDescription() === null
            && $invoiceItem->getDiscountAmount() == 0
            && $associatedOrder->getDiscountAmount() == 0
        ) {
            $discounts = [];
        } else {
            $discounts[] = [
                'label' => $associatedOrder->getDiscountDescription() ?? __('Discount'),
                'amount' => [
                    'value' => abs((float)$invoiceItem->getDiscountAmount()),
                    'currency' => $associatedOrder->getOrderCurrencyCode()
                ]
            ];
        }

        return $discounts;
    }
}
