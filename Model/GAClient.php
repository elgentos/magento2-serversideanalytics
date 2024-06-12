<?php
/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Br33f\Ga4\MeasurementProtocol\Dto\Event\PurchaseEvent;
use Br33f\Ga4\MeasurementProtocol\Dto\Parameter\ItemParameter;
use Br33f\Ga4\MeasurementProtocol\Dto\Request\BaseRequest;
use Br33f\Ga4\MeasurementProtocol\Service;
use Br33f\Ga4\MeasurementProtocol\Dto\Response\BaseResponse;
use Br33f\Ga4\MeasurementProtocol\Dto\Response\DebugResponse;
use DateTime;
use Magento\Store\Model\ScopeInterface;
use Elgentos\ServerSideAnalytics\Logger\Logger;

class GAClient
{

    const GOOGLE_ANALYTICS_SERVERSIDE_ENABLED                            = 'google/serverside_analytics/ga_enabled';
    const GOOGLE_ANALYTICS_SERVERSIDE_API_SECRET                         = 'google/serverside_analytics/api_secret';
    const GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID                     = 'google/serverside_analytics/measurement_id';
    const GOOGLE_ANALYTICS_SERVERSIDE_CURRENCY_SOURCE                    = 'google/serverside_analytics/currency_source';
    const GOOGLE_ANALYTICS_SERVERSIDE_DEBUG_MODE                         = 'google/serverside_analytics/debug_mode';
    const GOOGLE_ANALYTICS_SERVERSIDE_ENABLE_LOGGING                     = 'google/serverside_analytics/enable_logging';
    const GOOGLE_ANALYTICS_SERVERSIDE_FALLBACK_SESSION_ID_GENERATIONMODE = 'google/serverside_analytics/fallback_session_id_generation_mode';
    const GOOGLE_ANALYTICS_SERVERSIDE_FALLBACK_SESSION_ID_PREFIX         = 'google/serverside_analytics/fallback_session_id_prefix';
    const GOOGLE_ANALYTICS_SERVERSIDE_FALLBACK_SESSION_ID                = 'google/serverside_analytics/fallback_session_id';

    /**
     * @var Service
     */
    protected $service;

    /**
     * @var BaseRequest
     */
    protected $request;

    /**
     * @var PurchaseEvent
     */
    protected $purchaseEvent;

    /* Google Analytics Measurement Protocol API version */
    protected $version = '1';

    /* Count how many products are added to the Analytics object */
    protected $productCounter = 0;
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Elgentos\ServerSideAnalytics\Logger\Logger
     */
    private $logger;

    /**
     * Elgentos_ServerSideAnalytics_Model_GAClient constructor.
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\State $state,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Elgentos\ServerSideAnalytics\Logger\Logger $logger
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

        $this->service = new Service($this->getApiSecret());
        $this->service->setMeasurementId($this->getMeasurementId());

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
        return $this->scopeConfig->getValue(self::GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param \Magento\Framework\DataObject $data
     * @throws \Exception
     */
    public function setTrackingData(\Magento\Framework\DataObject $data)
    {
        if (!$data->getClientId()) {
            throw new \Exception('No client ID is set for GA client.');
        }

        $this->getRequest()->setClientId($data->getClientId()); // '2133506694.1448249699'

        $this->getRequest()->setTimestampMicros($this->getMicroTime());

        if ($data->getUserId()) {
            $this->getRequest()->setUserId($data->getUserId()); // magento customer_id
        }
    }

    /**
     * @param $data
     */
    public function setTransactionData($data)
    {
        foreach ($data->getData() as $key => $param) {
            $this->getPurchaseEvent()->setParamValue($key, $param);
        }

        $this->getPurchaseEvent()->setParamValue('timestamp_micros', $this->getMicroTime());
    }

    /**
     * @return Logger
     */
    public function getMicroTime(): string
    {
        $datetime = new DateTime();
        return $datetime->format('Uu');
    }

    /**
     * @param $products
     */
    public function addProducts($products)
    {
        foreach ($products as $product) {
            $this->addProduct($product);
        }
    }

    /**
     * @param $data
     */
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
            throw new \Exception(__('No products have been added to transaction %s', $this->getPurchaseEvent()->getTransactionId()));
        }

        $baseRequest = $this->getRequest();
        $baseRequest->addEvent($this->getPurchaseEvent());

        $baseRequest->validate();

        $send = $this->scopeConfig->isSetFlag(self::GOOGLE_ANALYTICS_SERVERSIDE_DEBUG_MODE, ScopeInterface::SCOPE_STORE) ? 'sendDebug' : 'send';

        $response = $this->getService()->$send($baseRequest);

        $this->createLog('Request: ', array($this->getRequest()->export()));
        $this->createLog('Response: ', array($response->getStatusCode()));
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    public function createLog($message, array $context = []) {
        if (!$this->scopeConfig->isSetFlag(self::GOOGLE_ANALYTICS_SERVERSIDE_ENABLE_LOGGING, ScopeInterface::SCOPE_STORE)) {
            return;
        }

        $this->logger->info($message, $context);
    }
}
