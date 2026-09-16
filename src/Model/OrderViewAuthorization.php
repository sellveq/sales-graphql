<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_SalesGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\SalesGraphQl\Model;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Config;
use ScandiPWA\SalesGraphQl\Api\OrderViewAuthorizationInterface;

class OrderViewAuthorization implements OrderViewAuthorizationInterface
{
    /**
     * @param Config $orderConfig
     */
    public function __construct(
        private readonly Config $orderConfig
    ) {}

    /**
     * {@inheritdoc}
     */
    public function canView(Order $order, $customerId): bool
    {
        $availableStatuses = $this->orderConfig->getVisibleOnFrontStatuses();

        if ($order->getId()
            && $order->getCustomerId()
            && (int)$order->getCustomerId() === (int)$customerId
            && in_array($order->getStatus(), $availableStatuses, true)
        ) {
            return true;
        }

        return false;
    }
}
