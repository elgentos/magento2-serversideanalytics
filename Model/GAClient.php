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
use DateTime;
use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Exception\TrackingDataNotValigException;
use Elgentos\ServerSideAnalytics\Logger\Logger;
use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class GAClient
{
    protected ?Service $service;

    protected ?BaseRequest $request;

    protected ?PurchaseEvent $purchaseEvent;

    protected string $version = '1';

    protected int $productCounter = 0;

    public function __construct(
        protected ModuleConfiguration $moduleConfiguration,
        protected Logger $logger
    ) {
    }

    public function setTrackingData(DataObject $data): void
    {
        if (!$data->getClientId()) {
            throw new TrackingDataNotValigException('No client ID is set for GA client.');
        }

        $this->getRequest()->setClientId($data->getClientId()); // '2133506694.1448249699'

        $this->getRequest()->setTimestampMicros($this->getMicroTime());

        if ($data->getUserId()) {
            $this->getRequest()->setUserId($data->getUserId()); // magento customer_id
        }
    }

    public function getRequest()
    {
        if (isset($this->request)) {
            return $this->request;
        }

        $this->request = new BaseRequest();

        return $this->request;
    }

    public function getMicroTime(): int
    {
        return (int)(new DateTime())
            ->format('Uu');
    }

    public function setTransactionData(DataObject $data)
    {
        foreach ($data->getData() as $key => $param) {
            $this->getPurchaseEvent()->setParamValue($key, $param);
        }

        $this->getPurchaseEvent()->setParamValue('timestamp_micros', $this->getMicroTime());
    }

    public function getPurchaseEvent()
    {
        if (isset($this->purchaseEvent)) {
            return $this->purchaseEvent;
        }

        $this->purchaseEvent = new PurchaseEvent();

        return $this->purchaseEvent;
    }

    /**
     * @param DataObject[] $products
     */
    public function addProducts(array $products): void
    {
        foreach ($products as $product) {
            $this->addProduct($product);
        }
    }

    public function addProduct(DataObject $data)
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
     * @throws LocalizedException
     */
    public function firePurchaseEvent()
    {
        if (!$this->productCounter) {
            throw new LocalizedException(
                __(
                    'No products have been added to transaction %s',
                    $this->getPurchaseEvent()->getTransactionId()
                )
            );
        }

        $baseRequest = $this->getRequest();
        $baseRequest->addEvent($this->getPurchaseEvent());

        $baseRequest->validate();

        $send = $this->moduleConfiguration->isDebugMode() ? 'sendDebug' : 'send';

        $response = $this->getService()->$send($baseRequest);

        $this->createLog('Request: ', [$this->getRequest()->export()]);
        $this->createLog('Response: ', [$response->getStatusCode()]);
    }

    public function getService()
    {
        if ($this->service) {
            return $this->service;
        }

        $this->service = new Service($this->moduleConfiguration->getApiSecret());
        $this->service->setMeasurementId($this->moduleConfiguration->getMeasurementId());

        return $this->service;
    }

    public function createLog($message, array $context = [])
    {
        if (!$this->moduleConfiguration->isLogging()) {
            return;
        }

        $this->logger->info($message, $context);
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }
}
