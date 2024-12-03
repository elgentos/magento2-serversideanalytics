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
use Elgentos\ServerSideAnalytics\Model\SalesOrderFactory;
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
        protected readonly SalesOrderFactory $elgentosSalesOrderFactory,
        protected readonly GAClient $gaclient,
        protected readonly SendPurchaseEvent $sendPurchaseEvent,
    ) {
    }

    public function execute(Observer $observer)
    {
        $quote = $observer->getQuote();
        $order = $observer->getOrder();

        if (!$order) {
            return;
        }

        if (!$this->moduleConfiguration->isReadyForUse($order->getStoreId())) {
            return;
        }

        if ($quote) {
            // Quote should've already been created.
            /** @var Collection $elgentosSalesOrderCollection */
            $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
            /** @var SalesOrder $elgentosSalesOrderData */
            $elgentosSalesOrderData = $elgentosSalesOrderCollection
                ->addFieldToFilter('quote_id', $quote->getId())
                ->getFirstItem();

            if (empty($elgentosSalesOrderData->getData('quote_id'))) {
                return;
            }

            $elgentosSalesOrderData->setData('order_id', $order->getId());
        }

        if (!isset($elgentosSalesOrderData)) {
            /** @var SalesOrder $elgentosSalesOrderData */
            $elgentosSalesOrderData = $this->elgentosSalesOrderFactory->create();
            $elgentosSalesOrderData->setData([
                'order_id' => $order->getId(),
                'quote_id' => $quote?->getId()
            ]);

            $elgentosSalesOrderData->setGaData(storeId: $order->getStoreId());
        }

        try {
            $this->elgentosSalesOrderRepository->save($elgentosSalesOrderData);
        } catch (\Exception $exception) {
            $this->gaclient->createLog($exception->getMessage());
        }

        /** @var Payment $payment */
        $payment = $order->getPayment();
        $method  = $payment->getMethodInstance();

        if (
            $this->moduleConfiguration->shouldTriggerOnPlaced(
                storeId: $order->getStoreId(),
                paymentMethodCode: $method->getCode()
            )
        ) {
            $this->sendPurchaseEvent->execute($order, 'AfterOrderPlaced');
        }
    }
}
