<?php
/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class TriggerEvent implements OptionSourceInterface
{
    public const SUBMIT  = 1;
    public const PAY = 2;

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return [
            ['value' => self::SUBMIT, 'label' => 'Right after placing the order'],
            ['value' => self::PAY, 'label' => 'When its payed'],
        ];
    }
}
