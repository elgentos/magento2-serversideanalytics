<?php

namespace Elgentos\ServerSideAnalytics\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Event\ObserverInterface;
use Elgentos\ServerSideAnalytics\Model\GAClient;
use Magento\Sales\Api\OrderRepositoryInterface;

class SendPurchaseEvent implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    private $emulation;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \Elgentos\ServerSideAnalytics\Model\GAClient
     */
    private $gaclient;
    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    private $event;

    private $orderRepository;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\App\Emulation $emulation,
        \Psr\Log\LoggerInterface $logger,
        \Elgentos\ServerSideAnalytics\Model\GAClient $gaclient,
        \Magento\Framework\Event\ManagerInterface $event,
        OrderRepositoryInterface $orderRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->emulation = $emulation;
        $this->logger = $logger;
        $this->gaclient = $gaclient;
        $this->event = $event;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @param $observer
     */
    public function execute(Observer $observer)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $observer->getPayment();

        /** @var \Magento\Sales\Model\Order $order */
        $orderId = $payment->getOrder()->getId();
        $orderStoreId = $payment->getOrder()->getStoreId();

        $this->emulation->startEnvironmentEmulation($orderStoreId, 'adminhtml');

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE)
        ) {
            $this->emulation->stopEnvironmentEmulation();
            return;
        }

        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getInvoice();

        $order = $this->orderRepository->get($orderId);
        $orderExtensionAttributes = $order->getExtensionAttributes();

        if (!$orderExtensionAttributes->getGaUserId()
                ||
            !$orderExtensionAttributes->getGaSessionId()
        ) {
            $this->emulation->stopEnvironmentEmulation();
            return;
        }

        $products = [];

        if ($this->scopeConfig->isSetFlag(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLE_LOGGING, ScopeInterface::SCOPE_STORE)) {
            $this->logger->info('elgentos_serversideanalytics_requests: GA UserID:' . $order->getExtensionAttributes()->getGaUserId());
        }

        /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
        foreach ($invoice->getAllItems() as $item) {
            if (!$item->isDeleted() && !$item->getOrderItem()->getParentItemId()) {
                $product = new DataObject([
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'price' => $this->getPaidProductPrice($item->getOrderItem()),
                    'quantity' => $item->getOrderItem()->getQtyOrdered(),
                    'position' => $item->getId()
                ]);

                $this->event->dispatch('elgentos_serversideanalytics_product_item_transport_object',
                    ['product' => $product, 'item' => $item]);

                $products[] = $product;
            }
        }

        $trackingDataObject = new DataObject([
            'client_id' => $orderExtensionAttributes->getGaUserId(),
            'ip_override' => $order->getRemoteIp(),
            'document_path' => '/checkout/onepage/success/'
        ]);

        $transactionDataObject = $this->getTransactionDataObject($order, $invoice);

        $this->sendPurchaseEvent($this->gaclient, $transactionDataObject, $products, $trackingDataObject);

        $this->emulation->stopEnvironmentEmulation();
    }

    /**
     * @param $order
     * @param $invoice
     *
     * @return DataObject
     */
    public function getTransactionDataObject($order, $invoice): DataObject
    {
        $transactionDataObject = new DataObject(
            [
                'transaction_id' => $order->getIncrementId(),
                'affiliation' => $order->getStoreName(),
                'currency' => $invoice->getGlobalCurrencyCode(),
                'revenue' => $invoice->getBaseGrandTotal(),
                'tax' => $invoice->getBaseTaxAmount(),
                'shipping' => ($this->getPaidShippingCosts($invoice) ?? 0),
                'coupon_code' => $order->getCouponCode(),
                'session_id' => $order->getExtensionAttributes()->getGaSessionId()
            ]
        );

        $this->event->dispatch('elgentos_serversideanalytics_transaction_data_transport_object',
            ['transaction_data_object' => $transactionDataObject]);

        return $transactionDataObject;
    }

    /**
     * @param $client
     * @param DataObject $transactionDataObject
     * @param array $products
     * @param DataObject $trackingDataObject
     */
    public function sendPurchaseEvent($client, DataObject $transactionDataObject, array $products, DataObject $trackingDataObject)
    {
        try {
            $client->setTransactionData($transactionDataObject);

            $client->addProducts($products);
        } catch (\Exception $e) {
            $this->logger->info($e);
            return;
        }

        try {
            $client->setTrackingData($trackingDataObject);

            $this->event->dispatch(
                'elgentos_serversideanalytics_tracking_data_transport_object',
                ['tracking_data_object' => $trackingDataObject]
            );
            $client->firePurchaseEvent();
        } catch (\Exception $e) {
            $this->logger->info($e);
        }
    }

    /**
     * Get the actual price the customer also saw in it's cart.
     *
     * @param \Magento\Sales\Model\Order\Item $orderItem
     *
     * @return float
     */
    private function getPaidProductPrice(\Magento\Sales\Model\Order\Item $orderItem)
    {
        return $this->scopeConfig->getValue('tax/display/type') == \Magento\Tax\Model\Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $orderItem->getBasePrice()
            : $orderItem->getBasePriceInclTax();
    }

    /**
     * @param \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return float
     */
    private function getPaidShippingCosts(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        return $this->scopeConfig->getValue('tax/display/type') == \Magento\Tax\Model\Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $invoice->getBaseShippingAmount()
            : $invoice->getBaseShippingInclTax();
    }
}
