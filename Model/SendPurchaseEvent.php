<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Logger\Logger;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\Source\CurrencySource;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item;
use Magento\Store\Model\App\Emulation;
use Magento\Tax\Model\Config;

class SendPurchaseEvent
{
    public function __construct(
        protected readonly ModuleConfiguration $moduleConfiguration,
        protected readonly Emulation $emulation,
        protected readonly Logger $logger,
        protected readonly GAClientFactory $GAClientFactory,
        protected readonly ManagerInterface $event,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly CollectionFactory $elgentosSalesOrderCollectionFactory,
        protected readonly SalesOrderRepository $elgentosSalesOrderRepository
    ) {
    }

    public function execute(Order $order, string $eventName = 'unknown')
    {
        /** @var Invoice $invoice */
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        $orderStoreId = $order->getStoreId();

        $gaUserDatabaseId = $order->getId();

        if (!$gaUserDatabaseId) {
            $gaUserDatabaseId = $order->getQuoteId();
        }

        if (!$gaUserDatabaseId) {
            return;
        }

        $this->emulation->startEnvironmentEmulation($orderStoreId, 'adminhtml');

        if (!$this->moduleConfiguration->isReadyForUse()) {
            $this->emulation->stopEnvironmentEmulation();

            return;
        }

        $elgentosSalesOrder = $this->getElgentosSalesOrder($gaUserDatabaseId);

        if (!$elgentosSalesOrder) {
            $this->emulation->stopEnvironmentEmulation();

            return;
        }

        $gaclient = $this->GAClientFactory->create();

        if ($elgentosSalesOrder->getData('send_at') !== null) {
            $this->emulation->stopEnvironmentEmulation();
            if ($this->moduleConfiguration->isLogging()) {
                $gaclient->createLog(
                    'The purchase event for order #' .
                    $order->getIncrementId() . ' was send already by trigger ' .
                    ($elgentosSalesOrder->getData('trigger') ?? '') . '.',
                    [
                        'eventName' => $eventName,
                        ...$elgentosSalesOrder->getData()
                    ]
                );
            }

            return;
        }

        if ($this->moduleConfiguration->isLogging()) {
            $gaclient->createLog(
                'Got ' . $eventName . ' event for Ga UserID: ' . $elgentosSalesOrder->getGaUserId(),
                [
                    'eventName' => $eventName,
                    ...$elgentosSalesOrder->getData()
                ]
            );
        }

        $trackingDataObject = new DataObject(
            [
                'client_id' => $elgentosSalesOrder->getGaUserId(),
                'ip_override' => $order->getRemoteIp(),
                'document_path' => '/checkout/onepage/success/'
            ]
        );

        $userId = $order->getCustomerId();
        if ($userId) {
            $trackingDataObject->setData('user_id', $userId);
        }

        $transactionDataObject = $this->getTransactionDataObject($order, $elgentosSalesOrder);
        $products = $this->collectProducts($order);
        $this->sendPurchaseEvent($gaclient, $transactionDataObject, $products, $trackingDataObject);

        $elgentosSalesOrder->setData('trigger', $eventName);
        $elgentosSalesOrder->setData('send_at', date('Y-m-d H:i:s'));
        $this->elgentosSalesOrderRepository->save($elgentosSalesOrder);

        $this->emulation->stopEnvironmentEmulation();
    }

    protected function getElgentosSalesOrder($gaUserDatabaseId): ?SalesOrder
    {
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        /** @var SalesOrder $elgentosSalesOrder */
        $elgentosSalesOrder = $elgentosSalesOrderCollection
            ->addFieldToFilter(
                ['quote_id', 'order_id'],
                [
                    ['eq' => $gaUserDatabaseId],
                    ['eq' => $gaUserDatabaseId]
                ]
            )
            ->getFirstItem();

        if (
            !$elgentosSalesOrder->getGaUserId()
            ||
            !$elgentosSalesOrder->getGaSessionId()
        ) {
            return null;
        }

        return $elgentosSalesOrder;
    }

    /**
     * @return DataObject[]
     */
    protected function collectProducts(Order $order): array
    {
        $products = [];

        /** @var Invoice\Item $item */
        foreach ($order->getAllItems() as $item) {
            if (!$item->isDeleted() && !$item->getParentItemId()) {
                $product = new DataObject(
                    [
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'price' => $this->getPaidProductPrice($item),
                        'quantity' => $item->getQtyOrdered(),
                        'position' => $item->getId(),
                        'item_brand' => $item->getProduct()?->getAttributeText('manufacturer')
                    ]
                );

                $this->event->dispatch(
                    'elgentos_serversideanalytics_product_item_transport_object',
                    ['product' => $product, 'item' => $item]
                );

                $products[] = $product;
            }
        }

        return $products;
    }

    /**
     * Get the actual price the customer also saw in it's cart.
     */
    private function getPaidProductPrice(Item $orderItem): float
    {
        return $this->moduleConfiguration->getTaxDisplayType() === Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $orderItem->getBasePrice()
            : $orderItem->getBasePriceInclTax();
    }

    public function getTransactionDataObject(Order $order, $elgentosSalesOrder): DataObject
    {
        $currency = $this->moduleConfiguration->getCurrencySource() === CurrencySource::GLOBAL ?
            $order->getGlobalCurrencyCode() :
            $order->getBaseCurrencyCode();

        $transactionDataObject = new DataObject(
            [
                'transaction_id' => $order->getIncrementId(),
                'affiliation' => $order->getStoreName(),
                'currency' => $currency,
                'value' => $order->getBaseGrandTotal(),
                'tax' => $order->getBaseTaxAmount(),
                'shipping' => ($this->getPaidShippingCosts($order) ?? 0),
                'coupon_code' => $order->getCouponCode(),
                'session_id' => $elgentosSalesOrder->getGaSessionId()
            ]
        );

        $this->event->dispatch(
            'elgentos_serversideanalytics_transaction_data_transport_object',
            ['transaction_data_object' => $transactionDataObject, 'order' => $order]
        );

        return $transactionDataObject;
    }

    private function getPaidShippingCosts(Order $order): ?float
    {
        return $this->moduleConfiguration->getTaxDisplayType() == Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $order->getBaseShippingAmount()
            : $order->getBaseShippingInclTax();
    }

    public function sendPurchaseEvent(
        GAClient $gaclient,
        DataObject $transactionDataObject,
        array $products,
        DataObject $trackingDataObject
    ): void {
        try {
            $gaclient->setTransactionData($transactionDataObject);

            $gaclient->addProducts($products);
        } catch (\Exception $e) {
            $gaclient->createLog($e);

            return;
        }

        try {
            $this->event->dispatch(
                'elgentos_serversideanalytics_tracking_data_transport_object',
                ['tracking_data_object' => $trackingDataObject]
            );

            $gaclient->setTrackingData($trackingDataObject);

            $gaclient->firePurchaseEvent();
        } catch (\Exception $e) {
            $gaclient->createLog($e);
        }
    }
}
