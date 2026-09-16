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

namespace ScandiPWA\SalesGraphQl\Model\Resolver;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\SalesGraphQl\Model\Resolver\Shipments as BaseShipments;

class Shipments extends BaseShipments
{
    /**
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly TimezoneInterface $timezone
    ) {
        parent::__construct($timezone);
    }

    /**
     * {@inheritdoc}
     * @throws LocalizedException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!isset($value['model']) && !($value['model'] instanceof OrderInterface)) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        $order = $value['model'];
        $shipments = $order->getShipmentsCollection()->getItems();

        if (empty($shipments)) {
            return [];
        }

        $orderShipments = [];
        foreach ($shipments as $shipment) {
            $orderShipments[] =
                [
                    'id' => base64_encode((string)$shipment->getEntityId()),
                    'number' => $shipment->getIncrementId(),
                    'comments' => $this->getShipmentComments($shipment),
                    'model' => $shipment,
                    'order' => $order
                ];
        }
        return $orderShipments;
    }

    /**
     * @param ShipmentInterface $shipment
     * @return array
     */
    private function getShipmentComments(ShipmentInterface $shipment): array
    {
        $comments = [];

        foreach ($shipment->getComments() as $comment) {
            if ($comment->getIsVisibleOnFront()) {
                $comments[] = [
                    'timestamp' => $this->timezone->date($comment->getCreatedAt())
                        ->format(DateTime::DATETIME_SLASH_PHP_FORMAT),
                    'message' => $comment->getComment()
                ];
            }
        }

        return $comments;
    }
}
