<?php
/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder;

use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder as SalesOrderResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Elgentos\ServerSideAnalytics\Model\SalesOrder;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(SalesOrder::class, SalesOrderResource::class);
    }
}
