<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CurrencySource implements OptionSourceInterface
{

    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return array(
            ['value' => 'order', 'label' => 'Currency of the order'],
            ['value' => 'global', 'label' => 'Default store currency'],
        );
    }
}
