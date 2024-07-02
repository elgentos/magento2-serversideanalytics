<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Payment\Helper\Data;

class PaymentMethods implements OptionSourceInterface
{
    public function __construct(
        protected Data $paymentHelper
    ) {
    }

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        $groupedMethods = [];
        foreach ($this->paymentHelper->getPaymentMethods() as $key => $method) {
            if (!isset($method['title'])) {
                continue;
            }

            $groupedMethods[$method['group'] ?? 'other'][] = ['value' => $key, 'label' => $method['title']];
        }

        ksort($groupedMethods);

        $options = [];
        foreach ($groupedMethods as $group => $methods) {
            $options[] = ['label' => ucfirst($group), 'value' => $methods];
        }

        return $options;
    }
}
