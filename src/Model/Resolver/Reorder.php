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

namespace ScandiPWA\SalesGraphQl\Model\Resolver;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\Reorder\Data\Error;
use Magento\Sales\Model\Reorder\Reorder as ResolveReorder;
use Magento\SalesGraphQl\Model\Resolver\Reorder as CoreReorder;

class Reorder extends CoreReorder
{
    // the schema argument name, repeated here because core declares its own constant private
    private const string ARGUMENT_ORDER_NUMBER = 'orderNumber';

    // these mirror core's private LOCK_PREFIX and LOCK_TIMEOUT, so a reorder on either path takes the same lock
    private const string LOCK_PREFIX = 'reorder_lock_';
    private const int LOCK_TIMEOUT = 60;

    /**
     * @param ResolveReorder $reorder
     * @param OrderFactory $orderFactory
     * @param LockManagerInterface $lockManager
     */
    public function __construct(
        private readonly ResolveReorder $reorder,
        private readonly OrderFactory $orderFactory,
        private readonly LockManagerInterface $lockManager
    ) {
        parent::__construct(
            $reorder,
            $orderFactory,
            $lockManager
        );
    }

    /**
     * {@inheritdoc}
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     * @throws AlreadyExistsException
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $currentUserId = $context->getUserId();
        $orderNumber = $args['orderNumber'] ?? '';

        // loaded by increment id alone, with the store read back off the order, so a reorder works across store views
        $order = $this->orderFactory->create()->loadByIncrementId($orderNumber);
        $orderStoreId = (string)$order->getStore()->getId();

        if ((int)$order->getCustomerId() !== $currentUserId) {
            throw new GraphQlInputException(
                __('Order number "%1" doesn\'t belong to the current customer', $orderNumber)
            );
        }

        $lockName = self::LOCK_PREFIX . hash('sha256', $orderNumber);

        if (!$this->lockManager->lock($lockName, self::LOCK_TIMEOUT)) {
            // core raises a bare LocalizedException here, which GraphQL masks as "Internal server error"
            throw new GraphQlInputException(
                __('Sorry, there has been an error processing your request. Please try again later.')
            );
        }

        try {
            $reorderOutput = $this->reorder->execute($orderNumber, $orderStoreId);
        } finally {
            $this->lockManager->unlock($lockName);
        }

        return [
            'cart' => [
                'model' => $reorderOutput->getCart(),
            ],
            'userInputErrors' => \array_map(
                function (Error $error) {
                    return [
                        'path' => [self::ARGUMENT_ORDER_NUMBER],
                        'code' => $error->getCode(),
                        'message' => $error->getMessage(),
                    ];
                },
                $reorderOutput->getErrors()
            )
        ];
    }
}
