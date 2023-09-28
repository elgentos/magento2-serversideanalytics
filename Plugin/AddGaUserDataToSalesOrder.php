<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Plugin;

use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\Collection;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Magento\Sales\Api\OrderRepositoryInterface;

class AddGaUserDataToSalesOrder
{
    private CollectionFactory $elgentosSalesOrderCollectionFactory;

    /**
     * AddGaUserDataToSalesOrder constructor.
     * @param CollectionFactory $acmeSalesOrderCollectionFactory
     */
    public function __construct(
        CollectionFactory $elgentosSalesOrderCollectionFactory
    ) {
        $this->elgentosSalesOrderCollectionFactory = $elgentosSalesOrderCollectionFactory;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param $result
     * @return mixed
     */
    public function afterGet(
        OrderRepositoryInterface $subject,
        $result
    ) {
        /** @var Collection $elgentosSalesOrder */
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        $elgentosSalesOrder = $elgentosSalesOrderCollection
            ->addFieldToFilter('order_id', $result->getId())
            ->getFirstItem();

        $extensionAttributes = $result->getExtensionAttributes();

        $extensionAttributes->setData('ga_user_id', $elgentosSalesOrder->getData('ga_user_id'));
        $extensionAttributes->setData('ga_session_id', $elgentosSalesOrder->getData('ga_session_id'));

        $result->setExtensionAttributes($extensionAttributes);

        return $result;
    }
}
