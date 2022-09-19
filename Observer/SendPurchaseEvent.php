<?php
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Event\ObserverInterface;
use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\UAClient;
use Magento\Tax\Model\Config;
use Psr\Log\LoggerInterface;

class SendPurchaseEvent implements ObserverInterface
{
    private ScopeConfigInterface $scopeConfig;
    private Emulation $emulation;
    private LoggerInterface $logger;
    private GAClient $gaClient;
    private UAClient $uaClient;
    private ManagerInterface $event;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Emulation $emulation,
        LoggerInterface $logger,
        GAClient $gaClient,
        UAClient $uaClient,
        ManagerInterface $event
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->emulation = $emulation;
        $this->logger = $logger;
        $this->gaClient = $gaClient;
        $this->uaClient = $uaClient;
        $this->event = $event;
    }

    public function execute(Observer $observer): void
    {
        /** @var Payment $payment */
        $payment = $observer->getPayment();

        $order = $payment->getOrder();

        $this->emulation->startEnvironmentEmulation($order->getStoreId(), 'adminhtml');

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE) &&
            !$this->scopeConfig->getValue(UAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE)
        ) {
            $this->emulation->stopEnvironmentEmulation();
            return;
        }

        /** @var Invoice $invoice */
        $invoice = $observer->getInvoice();

        if (!$order->getData('ga_user_id')) {
            $this->emulation->stopEnvironmentEmulation();
            return;
        }

        $products = [];

        /** @var Invoice\Item $item */
        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();

            if ($orderItem === null) {
                continue;
            }

            if (!$item->isDeleted() && !$orderItem->getParentItemId()) {
                $product = new DataObject([
                    'sku' => $item->getSku(),
                    'name' => $item->getName(),
                    'price' => $this->getPaidProductPrice($orderItem),
                    'quantity' => $orderItem->getQtyOrdered(),
                    'position' => $item->getId()
                ]);

                $this->event->dispatch('elgentos_serversideanalytics_product_item_transport_object',
                    ['product' => $product, 'item' => $item]);

                $products[] = $product;
            }
        }

        $trackingDataObject = new DataObject([
            'client_id' => $order->getData('ga_user_id'),
            'ip_override' => $order->getRemoteIp(),
            'document_path' => '/checkout/onepage/success/'
        ]);

        $transactionDataObject = $this->getTransactionDataObject($order, $invoice);

        if ($this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE))
        {
            $this->sendPurchaseEvent($this->gaClient, $transactionDataObject, $products, $trackingDataObject);
        }

        if ($this->scopeConfig->getValue(UAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE))
        {
            $ua = $this->scopeConfig->getValue(UAClient::GOOGLE_ANALYTICS_SERVERSIDE_UA, ScopeInterface::SCOPE_STORE);
            $uas = explode(',', $ua ?? '');
            $uas = array_filter($uas);
            $uas = array_map('trim', $uas);

            foreach ($uas as $ua) {
                $trackingDataObject->setData('tracking_id', $ua);
                $this->sendPurchaseEvent($this->uaClient, $transactionDataObject, $products, $trackingDataObject);
            }
        }
        
        $this->emulation->stopEnvironmentEmulation();
    }

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
                'coupon_code' => $order->getCouponCode()
            ]
        );

        $this->event->dispatch('elgentos_serversideanalytics_transaction_data_transport_object',
            ['transaction_data_object' => $transactionDataObject]);

        return $transactionDataObject;
    }

    public function sendPurchaseEvent($client, DataObject $transactionDataObject, array $products, DataObject $trackingDataObject): void
    {
        try {
            $client->setTransactionData($transactionDataObject);

            $client->addProducts($products);
        } catch (Exception $e) {
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
        } catch (Exception $e) {
            $this->logger->info($e);
        }
    }

    /**
     * Get the actual price the customer also saw in its cart.
     *
     * @param Item $orderItem
     *
     * @return float
     */
    private function getPaidProductPrice(Item $orderItem): float
    {
        return $this->scopeConfig->getValue('tax/display/type') == Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $orderItem->getBasePrice()
            : $orderItem->getBasePriceInclTax();
    }

    private function getPaidShippingCosts(Invoice $invoice): float
    {
        return $this->scopeConfig->getValue('tax/display/type') === Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $invoice->getBaseShippingAmount()
            : $invoice->getBaseShippingInclTax();
    }
}
