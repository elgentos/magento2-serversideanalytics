<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CurrencySource implements OptionSourceInterface
{
    const ORDER  = 1;
    const GLOBAL = 2;

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return array(
            ['value' => self::ORDER, 'label' => 'Currency of the order'],
            ['value' => self::GLOBAL, 'label' => 'Default store currency'],
        );
    }
}
