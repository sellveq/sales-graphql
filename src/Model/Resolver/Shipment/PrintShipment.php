<?php

/**
 * @category    ScandiPWA
 * @package     ScandiPWA_SalesGraphQl
 * @copyright   Modifications © Selveq. All rights reserved.
 * @license     OSL-3.0 (Open Software License ("OSL") v. 3.0)
 * See LICENSE for license details.
 */

namespace ScandiPWA\SalesGraphQl\Model\Resolver\Shipment;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\SalesGraphQl\Model\Formatter\Order as OrderFormatter;
use ScandiPWA\SalesGraphQl\Api\OrderViewAuthorizationInterface;

class PrintShipment implements ResolverInterface
{
    /**
     * @param ShipmentRepositoryInterface $shipmentRepository
     * @param OrderFormatter $orderFormatter
     * @param OrderViewAuthorizationInterface $orderViewAuthorizationInterface
     */
    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly OrderFormatter $orderFormatter,
        private readonly OrderViewAuthorizationInterface $orderViewAuthorizationInterface
    ) {}

    /**
     * {@inheritdoc}
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
        $customerId = $context->getUserId();

        // an absent, rejected or unowned id answers alike, so the error cannot say which shipment ids exist
        try {
            $shipment = $this->shipmentRepository->get($args['shipmentId']);
        } catch (NoSuchEntityException | InputException) {
            throw new GraphQlInputException(__('Current user is not allowed to print this order'));
        }

        $order = $shipment->getOrder();

        if (!$this->orderViewAuthorizationInterface->canView($order, $customerId)) {
            throw new GraphQlInputException(__('Current user is not allowed to print this order'));
        }

        return $this->orderFormatter->format($order);
    }
}
