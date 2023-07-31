<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class SalesOrder extends AbstractDb
{
    /** @var string Main table name */
    const MAIN_TABLE = 'elgentos_serversideanalytics_sales_order';

    /** @var string Main table primary key field name */
    const ID_FIELD_NAME = 'id';

    protected function _construct(): void
    {
        $this->_init(self::MAIN_TABLE, self::ID_FIELD_NAME);
    }
}
