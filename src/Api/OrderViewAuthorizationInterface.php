<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_SalesGraphQl
 * @copyright   Copyright © Scandiweb, Inc. All rights reserved.
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\SalesGraphQl\Api;

use Magento\Sales\Model\Order;

interface OrderViewAuthorizationInterface
{
    /**
     * @param Order $order
     * @param int $customerId
     * @return bool
     */
    public function canView(Order $order, $customerId);
}
