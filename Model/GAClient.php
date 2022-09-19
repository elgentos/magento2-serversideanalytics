<?php
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Br33f\Ga4\MeasurementProtocol\Dto\Event\PurchaseEvent;
use Br33f\Ga4\MeasurementProtocol\Dto\Parameter\ItemParameter;
use Br33f\Ga4\MeasurementProtocol\Dto\Request\BaseRequest;
use Br33f\Ga4\MeasurementProtocol\Service;
use Br33f\Ga4\MeasurementProtocol\Dto\Response\BaseResponse;
use Br33f\Ga4\MeasurementProtocol\Dto\Response\DebugResponse;
use Magento\Store\Model\ScopeInterface;

class GAClient
{
    public const GOOGLE_ANALYTICS_SERVERSIDE_ENABLED = 'google/serverside_analytics/ga_enabled';
    public const GOOGLE_ANALYTICS_SERVERSIDE_API_SECRET = 'google/serverside_analytics/api_secret';
    public const GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID = 'google/serverside_analytics/measurement_id';
    public const GOOGLE_ANALYTICS_SERVERSIDE_DEBUG_MODE = 'google/serverside_analytics/debug_mode';
    public const GOOGLE_ANALYTICS_SERVERSIDE_ENABLE_LOGGING = 'google/serverside_analytics/enable_logging';

    protected Service $service;

    /**
     * @var BaseRequest
     */
    protected BaseRequest $request;

    /**
     * @var PurchaseEvent
     */
    protected PurchaseEvent $purchaseEvent;

    /* Google Analytics Measurement Protocol API version */
    protected string $version = '4';

    /* Count how many products are added to the Analytics object */
    protected int $productCounter = 0;
    /**
     * @var \Magento\Framework\App\State
     */
    private \Magento\Framework\App\State $state;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private \Psr\Log\LoggerInterface $logger;

    /**
     * Elgentos_ServerSideAnalytics_Model_GAClient constructor.
     * @param  \Magento\Framework\App\State  $state
     * @param  \Magento\Framework\App\Config\ScopeConfigInterface  $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->state = $state;
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
    }

    public function getService()
    {
        if ($this->service) {
            return $this->service;
        }

        $this->service = new Service($this->getApiSecret(), $this->getMeasurementId());

        return $this->service;
    }

    public function getRequest()
    {
        if ($this->request) {
            return $this->request;
        }

        $this->request = new BaseRequest();

        return $this->request;
    }

    public function getPurchaseEvent()
    {
        if ($this->purchaseEvent) {
            return $this->purchaseEvent;
        }

        $this->purchaseEvent = new PurchaseEvent();

        return $this->purchaseEvent;
    }

    public function getApiSecret()
    {
        return $this->scopeConfig->getValue(self::GOOGLE_ANALYTICS_SERVERSIDE_API_SECRET, ScopeInterface::SCOPE_STORE);
    }

    public function getMeasurementId()
    {
        return $this->scopeConfig->getValue(self::GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID,
            ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param  \Magento\Framework\DataObject  $data
     * @throws \Exception
     */
    public function setTrackingData(\Magento\Framework\DataObject $data)
    {
        if (!$data->getClientId()) {
            throw new \Exception('No client ID is set for GA client.');
        }

        $this->getRequest()->setClientId($data->getClientId()); // '2133506694.1448249699'
    }

    public function setTransactionData($data)
    {
        $this->getPurchaseEvent()
            ->setTransactionId($data->getTransactionId())
            ->setCurrency($data->getCurrency())
            ->setValue($data->getRevenue())
            ->setTax($data->getTax())
            ->setShipping($data->getShipping());

        if ($data->getAffiliation()) {
            $this->getPurchaseEvent()->setAffiliation($data->getAffiliation());
        }

        if ($data->getCouponCode()) {
            $this->getPurchaseEvent()->setCouponCode($data->getCouponCode());
        }
    }

    public function addProducts($products)
    {
        foreach ($products as $product) {
            $this->addProduct($product);
        }
    }

    public function addProduct($data)
    {
        $this->productCounter++;

        $itemParameter = new ItemParameter($data->getData());
        $itemParameter->setItemId($data->getSku())
            ->setItemName($data->getName())
            ->setIndex($data->getPosition())
            ->setPrice($data->getPrice())
            ->setQuantity($data->getQuantity());

        $this->getPurchaseEvent()->addItem($itemParameter);
    }

    /**
     * @throws \Exception
     */
    public function firePurchaseEvent()
    {
        if (!$this->productCounter) {
            throw new \Exception(__('No products have been added to transaction %s',
                $this->getService()->getTransactionId()));
        }

        $this->getRequest()->addEvent($this->getPurchaseEvent())->validate();

        $send = $this->scopeConfig->isSetFlag(self::GOOGLE_ANALYTICS_SERVERSIDE_DEBUG_MODE) ? 'sendDebug' : 'send';

        /** @var $response BaseResponse|DebugResponse */
        $response = $this->getService()->$send($this->getRequest());

        // @codingStandardsIgnoreStart
        if ($this->scopeConfig->isSetFlag(self::GOOGLE_ANALYTICS_SERVERSIDE_DEBUG_MODE)) {
            $this->logger->info('elgentos_serversideanalytics_debug_response: ', array($response->getData()));
        }
        if ($this->scopeConfig->isSetFlag(self::GOOGLE_ANALYTICS_SERVERSIDE_ENABLE_LOGGING)) {
            $this->logger->info('elgentos_serversideanalytics_requests: ', array($this->getRequest()->export()));
        }
        // @codingStandardsIgnoreEnd
    }

    public function getVersion(): string
    {
        return $this->version;
    }

}