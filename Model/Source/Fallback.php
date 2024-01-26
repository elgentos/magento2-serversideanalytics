<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Fallback implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return array(
            ['value' => 1, 'label' => 'Enter one default sessionId'],
            ['value' => 2, 'label' => 'Completly random sessionId'],
            ['value' => 3, 'label' => 'Enter prefix for random sessionId']
        );
    }
}
