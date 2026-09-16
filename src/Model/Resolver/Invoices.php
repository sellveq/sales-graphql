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

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\Stdlib\DateTime;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\SalesGraphQl\Model\Resolver\Invoices as SourceInvoices;

class Invoices extends SourceInvoices
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
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        if (!(($value['model'] ?? null) instanceof OrderInterface)) {
            throw new LocalizedException(__('"model" value should be specified'));
        }

        /** @var OrderInterface $orderModel */
        $orderModel = $value['model'];
        $invoices = [];

        /** @var InvoiceInterface $invoice */
        foreach ($orderModel->getInvoiceCollection() as $invoice) {
            $invoices[] = [
                'id' => base64_encode((string)$invoice->getEntityId()),
                'number' => $invoice['increment_id'],
                'comments' => $this->getInvoiceComments($invoice),
                'model' => $invoice,
                'order' => $orderModel
            ];
        }

        return $invoices;
    }

    /**
     * @param InvoiceInterface $invoice
     * @return array
     */
    private function getInvoiceComments(InvoiceInterface $invoice): array
    {
        $comments = [];

        foreach ($invoice->getComments() as $comment) {
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
