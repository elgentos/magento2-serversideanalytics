<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Magento\Framework\ObjectManagerInterface;

class SalesOrderRepository
{
    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager = null;

    /**
     * Instance name to create
     *
     * @var string
     */
    protected $_instanceName = null;

    /**
     * Factory constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param string                                    $instanceName
     */
    public function __construct(
        \Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder $resource,
    ) {
        $this->resource  = $resource;
    }

    public function save($data){
        $this->resource->save($data);
    }
}
