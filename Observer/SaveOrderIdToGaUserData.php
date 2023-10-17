<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Observer;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Event\ObserverInterface;

class SaveOrderIdToGaUserData implements ObserverInterface
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly CollectionFactory $elgentosSalesOrderCollectionFactory,
        private readonly SalesOrderRepository $elgentosSalesOrderRepository,
        private readonly GAClient $gaclient
    ) {
    }

    public function execute(Observer $observer)
    {

        if (!$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE)
        ) {
            return;
        }

        $quote = $observer->getQuote();
        $order = $observer->getOrder();

        if (!$quote) {
            return;
        }

        if (!$order) {
            return;
        }

        /** @var Collection $elgentosSalesOrderCollection */
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        $elgentosSalesOrderData = $elgentosSalesOrderCollection
            ->addFieldToFilter('quote_id', $quote->getId())
            ->getFirstItem();

        if (!$elgentosSalesOrderData) {
            return;
        }

        $elgentosSalesOrderData->setData('order_id', $order->getId());

        try {
            $this->elgentosSalesOrderRepository->save($elgentosSalesOrderData);
        } catch (\Exception $exception) {
            $this->logger->info($exception->getMessage());
        }

        return;
    }
}
