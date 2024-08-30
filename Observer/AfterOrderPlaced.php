<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Observer;

use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\Collection;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Elgentos\ServerSideAnalytics\Model\SendPurchaseEvent;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order\Payment;

class AfterOrderPlaced implements ObserverInterface
{
    public function __construct(
        protected readonly ModuleConfiguration $moduleConfiguration,
        protected readonly CollectionFactory $elgentosSalesOrderCollectionFactory,
        protected readonly SalesOrderRepository $elgentosSalesOrderRepository,
        protected readonly GAClient $gaclient,
        protected readonly SendPurchaseEvent $sendPurchaseEvent,
    ) {
    }

    public function execute(Observer $observer)
    {

        if (!$this->moduleConfiguration->isReadyForUse()) {
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
        $elgentosSalesOrderData       = $elgentosSalesOrderCollection
            ->addFieldToFilter('quote_id', $quote->getId())
            ->getFirstItem();

        if (empty($elgentosSalesOrderData->getData('quote_id'))) {
            return;
        }

        $elgentosSalesOrderData->setData('order_id', $order->getId());

        try {
            $this->elgentosSalesOrderRepository->save($elgentosSalesOrderData);
        } catch (\Exception $exception) {
            $this->gaclient->createLog($exception->getMessage());
        }

        /** @var Payment $payment */
        $payment = $order->getPayment();
        $method  = $payment->getMethodInstance();

        if ($this->moduleConfiguration->shouldTriggerOnPlaced(paymentMethodCode: $method->getCode())) {
            $this->sendPurchaseEvent->execute($order, 'AfterOrderPlaced');
        }
    }
}
