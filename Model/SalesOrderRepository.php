<?php

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

class SalesOrderRepository
{
    public function __construct(
        private readonly \Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder $resource,
    ) {
    }

    public function save($data){
        $this->resource->save($data);
    }
}
