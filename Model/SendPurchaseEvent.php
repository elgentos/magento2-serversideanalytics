<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Helper\UserDataHelper;
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

        if (!$this->moduleConfiguration->isReadyForUse($orderStoreId)) {
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
            if ($this->moduleConfiguration->isLogging($orderStoreId)) {
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

        if ($this->moduleConfiguration->isLogging($orderStoreId)) {
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
        $userData = $this->collectUserData($order);

        $this->sendPurchaseEvent($gaclient, $transactionDataObject, $products, $trackingDataObject, $userData, $orderStoreId);

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

    protected function collectUserData(Order $order){
        $userDataHelper = new UserDataHelper();

        $customerEmail = $order->getCustomerEmail();

        if ($customerEmail) {
            $userDataHelper->setEmail($customerEmail);
        }

        // Get billing address and set phone number and address details
        $billingAddress = $order->getBillingAddress();

        if ($billingAddress) {
            $billingPhoneNumber = $billingAddress->getTelephone();
            if ($billingPhoneNumber) {
                $userDataHelper->setPhoneNumber($billingPhoneNumber);
            }

            // Add address
            $userDataHelper->addAddress(
                $billingAddress->getFirstname(),
                $billingAddress->getLastname(),
                implode(' ', $billingAddress->getStreet()),
                $billingAddress->getCity(),
                $billingAddress->getRegion(),
                $billingAddress->getPostcode(),
                $billingAddress->getCountryId()
            );
        }

        // Optionally process shipping address if needed
        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress) {
            $shippingPhoneNumber = $shippingAddress->getTelephone();
            if ($shippingPhoneNumber) {
                $userDataHelper->setPhoneNumber($shippingPhoneNumber);
            }

            $userDataHelper->addAddress(
                $shippingAddress->getFirstname(),
                $shippingAddress->getLastname(),
                implode(' ', $shippingAddress->getStreet()),
                $shippingAddress->getCity(),
                $shippingAddress->getRegion(),
                $shippingAddress->getPostcode(),
                $shippingAddress->getCountryId()
            );
        }

        return $userDataHelper->toArray();
    }

    /**
     * Get the actual price the customer also saw in it's cart.
     * @return float
     */
    private function getPaidProductPrice(Item $orderItem): float
    {
        $price = $this->moduleConfiguration
            ->getTaxDisplayType($orderItem->getOrder()?->getStoreId()) === Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $orderItem->getBasePrice()
            : $orderItem->getBasePriceInclTax();

        return (float)$price;
    }

    public function getTransactionDataObject(Order $order, $elgentosSalesOrder): DataObject
    {
        $currency = $this->moduleConfiguration->getCurrencySource($order->getStoreId()) === CurrencySource::GLOBAL ?
            $order->getGlobalCurrencyCode() :
            $order->getBaseCurrencyCode();

        $shippingCosts = $this->getPaidShippingCosts($order);

        $transactionDataObject = new DataObject(
            [
                'transaction_id' => $order->getIncrementId(),
                'affiliation' => $order->getStoreName(),
                'currency' => $currency,
                'value' => (float)$order->getBaseGrandTotal(),
                'tax' => (float)$order->getBaseTaxAmount(),
                'shipping' => $shippingCosts ?? 0.0, // Use 0.0 if null
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

    /**
     * Get shipping costs
     * @return float|null
     */
    private function getPaidShippingCosts(Order $order): ?float
    {
        $shippingAmount = $this->moduleConfiguration->getTaxDisplayType($order->getStoreId()) == Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $order->getBaseShippingAmount()
            : $order->getBaseShippingInclTax();

        if ($shippingAmount === null) {
            return null;
        }

        return (float)$shippingAmount;
    }

    public function sendPurchaseEvent(
        GAClient $gaclient,
        DataObject $transactionDataObject,
        array $products,
        DataObject $trackingDataObject,
        array $userData,
        int|string|null $orderStoreId
    ): void {
        try {
            $gaclient->setTransactionData($transactionDataObject);
            $gaclient->addUserDataItems($userData);
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

            $gaclient->firePurchaseEvent($orderStoreId);
        } catch (\Exception $e) {
            $gaclient->createLog($e);
        }
    }
}
