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

namespace ScandiPWA\SalesGraphQl\Model\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesGraphQl\Model\Order\OrderPayments as CoreOrderPayments;

class OrderPayments extends CoreOrderPayments
{
    /**
     * {@inheritdoc}
     */
    public function getOrderPaymentMethod(OrderInterface $orderModel): array
    {
        $orderPayment = $orderModel->getPayment();

        if (!$orderPayment) {
            return [];
        }

        return [
            [
                'name' => $orderPayment->getAdditionalInformation()['method_title'] ?? '',
                'type' => $orderPayment->getMethod(),
                'additional_data' => [],
                'purchase_number' => $orderPayment->getPoNumber()
            ]
        ];
    }
}
