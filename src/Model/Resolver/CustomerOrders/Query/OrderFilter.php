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

namespace ScandiPWA\SalesGraphQl\Model\Resolver\CustomerOrders\Query;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\InputException;
use Magento\Sales\Model\Order\Config;
use Magento\SalesGraphQl\Model\Resolver\CustomerOrders\Query\OrderFilter as CoreOrderFilter;

class OrderFilter extends CoreOrderFilter
{
    /**
     * graphql field name to order collection column
     * @var string[]
     */
    protected array $fieldTranslatorArray = [
        'number' => 'increment_id',
    ];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param Config $orderConfig
     * @param string[] $fieldTranslatorArray
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
        private readonly Config $orderConfig,
        array $fieldTranslatorArray = []
    ) {
        parent::__construct(
            $filterBuilder,
            $filterGroupBuilder,
            $fieldTranslatorArray
        );

        $this->fieldTranslatorArray = array_replace($this->fieldTranslatorArray, $fieldTranslatorArray);
    }

    /**
     * {@inheritdoc}
     */
    public function createFilterGroups(
        array $args,
        int $userId,
        int $storeId,
        array $storeIds
    ): array {
        $filterGroups = [];

        $this->filterGroupBuilder->setFilters(
            [$this->filterBuilder->setField('customer_id')->setValue($userId)->setConditionType('eq')->create()]
        );
        $filterGroups[] = $this->filterGroupBuilder->create();

        // a status the merchant hid from the storefront hides the order from the list, as canView() does
        $this->filterGroupBuilder->setFilters(
            [
                $this->filterBuilder->setField('status')
                    ->setValue($this->orderConfig->getVisibleOnFrontStatuses())
                    ->setConditionType('in')
                    ->create()
            ]
        );
        $filterGroups[] = $this->filterGroupBuilder->create();

        $storeIds[] = $storeId;
        $this->filterGroupBuilder->setFilters(
            [$this->filterBuilder->setField('store_id')->setValue($storeIds)->setConditionType('in')->create()]
        );
        $filterGroups[] = $this->filterGroupBuilder->create();

        if (isset($args['filter'])) {
            foreach ($args['filter'] as $field => $cond) {
                if (isset($this->fieldTranslatorArray[$field])) {
                    $field = $this->fieldTranslatorArray[$field];
                }

                $filters = [];

                foreach ($cond as $condType => $value) {
                    if ($condType === 'match') {
                        if (is_array($value)) {
                            throw new InputException(__('Invalid match filter'));
                        }

                        $searchValue = $value !== null ? str_replace('%', '', $value) : '';
                        $filters[] = $this->filterBuilder->setField($field)
                            ->setValue("%$searchValue%")
                            ->setConditionType('like')
                            ->create();
                    } else {
                        $filters[] = $this->filterBuilder->setField($field)
                            ->setValue($value)
                            ->setConditionType($condType)
                            ->create();
                    }
                }

                $this->filterGroupBuilder->setFilters($filters);
                $filterGroups[] = $this->filterGroupBuilder->create();
            }
        }

        return $filterGroups;
    }
}
