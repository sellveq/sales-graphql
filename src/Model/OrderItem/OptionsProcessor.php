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

namespace ScandiPWA\SalesGraphQl\Model\OrderItem;

use Magento\Downloadable\Api\LinkRepositoryInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\SalesGraphQl\Model\OrderItem\OptionsProcessor as SourceOptionsProcessor;

class OptionsProcessor extends SourceOptionsProcessor
{
    /** @var string[] */
    protected $selectedOptionAllowedTypes = ['field', 'area', 'file', 'date', 'date_time', 'time'];

    /** @var string[] */
    protected $enteredOptionAllowedTypes = ['drop_down', 'radio', 'checkbox', 'multiple'];

    /** @var array */
    protected $selectedOptions = [];

    /** @var array */
    protected $enteredOptions = [];

    /**
     * @param LinkRepositoryInterface $linkRepository
     */
    public function __construct(
        private readonly LinkRepositoryInterface $linkRepository
    ) {}

    /**
     * {@inheritdoc}
     */
    public function getItemOptions(OrderItemInterface $orderItem): array
    {
        $this->selectedOptions = [];
        $this->enteredOptions = [];
        $options = $orderItem->getProductOptions();

        if ($options) {
            if (isset($options['options'])) {
                $this->processOptions($options['options']);
            }

            if (isset($options['attributes_info'])) {
                $this->processAttributesInfo($options['attributes_info']);
            }

            if (isset($options['bundle_options'])) {
                $this->processBundleOptions($options['bundle_options']);
            }

            if (isset($options['links'])) {
                $this->processDownloadableLinksOptions($orderItem, $options['links']);
            }
        }

        return ['selected_options' => $this->selectedOptions, 'entered_options' => $this->enteredOptions];
    }

    /**
     * @param array $options
     * @return void
     */
    protected function processOptions(array $options)
    {
        foreach ($options as $option) {
            if (isset($option['option_type'])) {
                if (in_array($option['option_type'], $this->selectedOptionAllowedTypes)) {
                    if ($option['option_type'] === 'file') {
                        $value = $option['value'];
                    } else {
                        $value = $option['print_value'] ?? $option['value'];
                    }

                    $this->selectedOptions[] = [
                        'label' => $option['label'],
                        'value' => $value,
                        'type' => $option['option_type'] ?? null
                    ];
                } elseif (in_array($option['option_type'], $this->enteredOptionAllowedTypes)) {
                    $this->enteredOptions[] = [
                        'label' => $option['label'],
                        'value' => $option['print_value'] ?? $option['value'],
                        'type' => $option['option_type'] ?? null
                    ];
                }
            }
        }
    }

    /**
     * @param array $attributesInfo
     * @return void
     */
    protected function processAttributesInfo(array $attributesInfo)
    {
        foreach ($attributesInfo as $option) {
            $this->selectedOptions[] = [
                'label' => $option['label'],
                'value' => $option['print_value'] ?? $option['value'],
            ];
        }
    }

    /**
     * @param array $bundleOptions
     * @return void
     */
    protected function processBundleOptions(array $bundleOptions)
    {
        foreach ($bundleOptions as $option) {
            $this->enteredOptions[] = [
                'label' => $option['label'],
                'items' => $option['value']
            ];
        }
    }

    /**
     * @param mixed $product
     * @param string[] $links
     * @return void
     */
    protected function processDownloadableLinksOptions($product, array $links)
    {
        $productLinks = $this->linkRepository->getList($product->getSku());
        $linksOutput = ['label' => 'Links', 'linkItems' => []];

        foreach ($productLinks as $link) {
            if (in_array($link->getId(), $links)) {
                $linksOutput['linkItems'][] = $link->getTitle();
            }
        }

        if (count($linksOutput['linkItems'])) {
            $this->selectedOptions[] = $linksOutput;
        }
    }
}
