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

namespace ScandiPWA\SalesGraphQl\Model\Formatter;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Rss\UrlBuilderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Rss\Signature;
use Magento\SalesGraphQl\Model\Formatter\Order as SourceOrder;
use Magento\SalesGraphQl\Model\Order\OrderAddress;
use Magento\SalesGraphQl\Model\Order\OrderPayments;
use Magento\Store\Model\ScopeInterface;

class Order extends SourceOrder
{
    // core's own RSS config path, so the field honours the same Stores > Configuration flag as core's feed
    private const string XML_PATH_ORDER_RSS_ENABLED_STATUS = 'rss/order/status';

    /**
     * @param OrderAddress $orderAddress
     * @param OrderPayments $orderPayments
     * @param UrlBuilderInterface $rssUrlBuilder
     * @param Signature $signature
     * @param ScopeConfigInterface $scopeConfig
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly OrderAddress $orderAddress,
        private readonly OrderPayments $orderPayments,
        private readonly UrlBuilderInterface $rssUrlBuilder,
        private readonly Signature $signature,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly TimezoneInterface $timezone
    ) {
        parent::__construct(
            $orderAddress,
            $orderPayments,
            $timezone
        );
    }

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */
    public function format(OrderInterface $orderModel): array
    {
        return [
            'created_at' => $orderModel->getCreatedAt(),
            'grand_total' => $orderModel->getGrandTotal(),
            'id' => base64_encode((string)$orderModel->getEntityId()),
            'increment_id' => $orderModel->getIncrementId(),
            'number' => $orderModel->getIncrementId(),
            'order_date' => $this->timezone->date($orderModel->getCreatedAt())
                ->format(DateTime::DATETIME_SLASH_PHP_FORMAT),
            'order_number' => $orderModel->getIncrementId(),
            'status' => $orderModel->getStatusLabel(),
            'email' => $orderModel->getCustomerEmail(),
            'shipping_method' => $orderModel->getShippingDescription(),
            'shipping_address' => $this->orderAddress->getOrderShippingAddress($orderModel),
            'billing_address' => $this->orderAddress->getOrderBillingAddress($orderModel),
            'payment_methods' => $this->orderPayments->getOrderPaymentMethod($orderModel),
            'applied_coupons' => $orderModel->getCouponCode() ? [['code' => $orderModel->getCouponCode()]] : [],
            'rss_link' => $this->getRssLink($orderModel),
            'can_reorder' => $orderModel->canReorder(),
            'model' => $orderModel,
            'comments' => $this->getOrderComments($orderModel)
        ];
    }

    /**
     * @param OrderInterface $order
     * @return string|null
     */
    public function getRssLink($order)
    {
        if (!$this->isRssAllowed()) {
            return null;
        }

        return $this->rssUrlBuilder->getUrl($this->getLinkParams($order));
    }

    /**
     * retrieve the order status url key
     * @param OrderInterface $order
     * @return string
     */
    protected function getUrlKey($order)
    {
        $data = [
            'order_id' => $order->getId(),
            'increment_id' => $order->getIncrementId(),
            'customer_id' => $order->getCustomerId(),
        ];

        return base64_encode(json_encode($data));
    }

    /**
     * get type, secure and query params for the link
     * @param OrderInterface $order
     * @return array
     */
    protected function getLinkParams($order)
    {
        $data = $this->getUrlKey($order);

        return [
            'type' => 'order_status',
            '_secure' => true,
            '_query' => ['data' => $data, 'signature' => $this->signature->signData($data)],
        ];
    }

    /**
     * check whether order status notification is allowed
     * @return bool
     */
    public function isRssAllowed()
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ORDER_RSS_ENABLED_STATUS,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderComments(OrderInterface $order): array
    {
        $comments = [];

        foreach ($order->getStatusHistories() as $comment) {
            if ($comment->getIsVisibleOnFront()) {
                $comments[] = [
                    'timestamp' => $comment->getCreatedAt(),
                    'message' => $comment->getComment()
                ];
            }
        }

        return $comments;
    }
}
