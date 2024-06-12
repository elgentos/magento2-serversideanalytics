<?php
/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
declare(strict_types=1);


namespace Elgentos\ServerSideAnalytics\Model;

use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder as SalesOrderResource;

class SalesOrderRepository
{
    public function __construct(
        private readonly SalesOrderResource $resource,
    ) {
    }

    public function save($data)
    {
        $this->resource->save($data);
    }
}
