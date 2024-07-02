<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TriggerMode implements OptionSourceInterface
{
    public const PLACED  = 1;
    public const PAYED = 2;
    public const PAYMENT_METHOD_DEPENDENT  = 90;
    public const ALL_OPTION_VALUES  =  [self::PLACED, self::PAYED, self::PAYMENT_METHOD_DEPENDENT];

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::PLACED, 'label' => 'Right after placing the order'],
            ['value' => self::PAYED, 'label' => 'When the order is payed'],
            ['value' => self::PAYMENT_METHOD_DEPENDENT, 'label' => 'Depends on the payment method..'],
        ];
    }
}
