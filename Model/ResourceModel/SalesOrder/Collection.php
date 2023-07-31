<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Elgentos\ServerSideAnalytics\Model\SalesOrder;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(SalesOrder::class, \Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder::class);
    }
}
