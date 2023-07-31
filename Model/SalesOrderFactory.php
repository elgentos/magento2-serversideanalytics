<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Magento\Framework\ObjectManagerInterface;

class SalesOrderFactory
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
        \Magento\Framework\ObjectManagerInterface $objectManager,
        $instanceName = '\Elgentos\ServerSideAnalytics\Model\SalesOrder'
    ) {
        $this->_objectManager = $objectManager;
        $this->_instanceName  = $instanceName;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function create(array $data = [])
    {
        return $this->_objectManager->create($this->_instanceName, $data);
    }
}
