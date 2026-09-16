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

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesGraphQl\Model\Order\OrderAddress as CoreOrderAddress;

class OrderAddress extends CoreOrderAddress
{
    /**
     * {@inheritdoc}
     */
    public function getOrderShippingAddress(
        OrderInterface $order
    ): ?array {
        $shippingAddress = null;

        if ($order->getShippingAddress()) {
            $shippingAddress = $this->formatAddressData($order->getShippingAddress());
        }

        return $shippingAddress;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderBillingAddress(
        OrderInterface $order
    ): ?array {
        $billingAddress = null;

        if ($order->getBillingAddress()) {
            $billingAddress = $this->formatAddressData($order->getBillingAddress());
        }

        return $billingAddress;
    }

    /**
     * @param OrderAddressInterface $orderAddress
     * @return array
     */
    public function formatAddressData(
        OrderAddressInterface $orderAddress
    ): array {
        // the theme reads country_id, but 2.4.9 still declares country_code, so both keys are answered
        return [
            'firstname' => $orderAddress->getFirstname(),
            'lastname' => $orderAddress->getLastname(),
            'middlename' => $orderAddress->getMiddlename(),
            'postcode' => $orderAddress->getPostcode(),
            'prefix' => $orderAddress->getPrefix(),
            'suffix' => $orderAddress->getSuffix(),
            'street' => $orderAddress->getStreet(),
            'country_code' => $orderAddress->getCountryId(),
            'country_id' => $orderAddress->getCountryId(),
            'city' => $orderAddress->getCity(),
            'company' => $orderAddress->getCompany(),
            'fax' => $orderAddress->getFax(),
            'telephone' => $orderAddress->getTelephone(),
            'vat_id' => $orderAddress->getVatId(),
            'region_id' => $orderAddress->getRegionId(),
            'region' => $orderAddress->getRegion()
        ];
    }
}
