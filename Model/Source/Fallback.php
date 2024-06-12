<?php
/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Fallback implements OptionSourceInterface
{
    public const DEFAULT = 1;
    public const RANDOM  = 2;
    public const PREFIX  = 3;
    /**
     * Return array of options as value-label pairs
     *
     * @return array Format: array(array('value' => '<value>', 'label' => '<label>'), ...)
     */
    public function toOptionArray()
    {
        return array(
            ['value' => self::DEFAULT, 'label' => 'Enter one default sessionId'],
            ['value' => self::RANDOM, 'label' => 'Completly random sessionId'],
            ['value' => self::PREFIX, 'label' => 'Enter prefix for random sessionId']
        );
    }
}
