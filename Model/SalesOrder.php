<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Magento\Framework\Model\AbstractModel;

class SalesOrder extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\SalesOrder::class);
    }
}
