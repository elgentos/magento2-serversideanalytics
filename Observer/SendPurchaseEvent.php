<?php

namespace Elgentos\ServerSideAnalytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class SendPurchaseEvent implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
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

    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Psr\Log\LoggerInterface $logger,
                                \Elgentos\ServerSideAnalytics\Model\GAClient $gaclient,
                                \Magento\Framework\Event\ManagerInterface $event)
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->gaclient = $gaclient;
        $this->event = $event;
    }

    /**
     * @param $observer
     */
    public function execute(Observer $observer)
    {
        if (!$this->scopeConfig->getValue(\Magento\GoogleAnalytics\Helper\Data::XML_PATH_ACTIVE)) {
            return;
        }
        if (!$this->scopeConfig->getValue(\Magento\GoogleAnalytics\Helper\Data::XML_PATH_ACCOUNT)) {
            $this->logger->info('Google Analytics extension and ServerSideAnalytics extension are activated but no Google Analytics account number has been found.');
            return;
        }


        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $observer->getPayment();
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $invoice = $observer->getInvoice();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        if (!$order->getGaUserId()) {
            return;
        }

        /** @var \Elgentos\ServerSideAnalytics\Model\GAClient $client */
        $client = $this->gaclient;

        try {
            $trackingDataObject = new \Magento\Framework\DataObject([
                'tracking_id' => $this->scopeConfig->getValue(\Magento\GoogleAnalytics\Helper\Data::XML_PATH_ACCOUNT),
                'client_id' => $order->getGaUserId(),
                'ip_override' => $order->getRemoteIp(),
                'document_path' => '/checkout/onepage/success/'
            ]);

            $this->event->dispatch('elgentos_serversideanalytics_tracking_data_transport_object', ['tracking_data_object' => $trackingDataObject]);
            $client->setTrackingData($trackingDataObject);

            $client->setTransactionData(
                new \Magento\Framework\DataObject(
                    [
                        'transaction_id' => $order->getIncrementId(),
                        'affiliation' => $order->getStoreName(),
                        'revenue' => $invoice->getBaseGrandTotal(),
                        'tax' => $invoice->getTaxAmount(),
                        'shipping' => $this->getPaidShippingCosts($invoice),
                        'coupon_code' => $order->getCouponCode()
                    ]
                )
            );

            $products = [];
            /** @var \Magento\Sales\Model\Order\Invoice\Item $item */
            foreach ($invoice->getAllItems() as $item) {
                if (!$item->isDeleted() && !$item->getOrderItem()->getParentItemId()) {
                    $product = new \Magento\Framework\DataObject([
                        'sku' => $item->getSku(),
                        'name' => $item->getName(),
                        'price' => $this->getPaidProductPrice($item->getOrderItem()),
                        'quantity' => $item->getOrderItem()->getQtyOrdered(),
                        'position' => $item->getId()
                    ]);
                    $this->event->dispatch('elgentos_serversideanalytics_product_item_transport_object', ['product' => $product, 'item' => $item]);
                    $products[] = $product;
                }
            }

            $client->addProducts($products);

            $client->firePurchaseEvent();
        } catch (\Exception $e) {
           $this->logger->info($e);
        }
    }

    /**
     * Get the actual price the customer also saw in it's cart.
     *
     * @param  \Magento\Sales\Model\Order\Item $orderItem
     *
     * @return float
     */
    private function getPaidProductPrice(\Magento\Sales\Model\Order\Item $orderItem)
    {
        return $this->scopeConfig->getValue('tax/display/type') == \Magento\Tax\Model\Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $orderItem->getPrice()
            : $orderItem->getPriceInclTax();
    }

    /**
     * @param  \Magento\Sales\Model\Order\Invoice $invoice
     *
     * @return float
     */
    private function getPaidShippingCosts(\Magento\Sales\Model\Order\Invoice $invoice)
    {
        return $this->scopeConfig->getValue('tax/display/type') == \Magento\Tax\Model\Config::DISPLAY_TYPE_EXCLUDING_TAX
            ? $invoice->getShippingAmount()
            : $invoice->getShippingInclTax();
    }

}