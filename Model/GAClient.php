<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Br33f\Ga4\MeasurementProtocol\Dto\Common\UserAddress;
use Br33f\Ga4\MeasurementProtocol\Dto\Common\UserDataItem;
use Br33f\Ga4\MeasurementProtocol\Dto\Event\PurchaseEvent;
use Br33f\Ga4\MeasurementProtocol\Dto\Parameter\ItemParameter;
use Br33f\Ga4\MeasurementProtocol\Dto\Request\BaseRequest;
use Br33f\Ga4\MeasurementProtocol\Service;
use DateTime;
use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Exception\TrackingDataNotValidException;
use Elgentos\ServerSideAnalytics\Logger\Logger;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;

class GAClient
{
    /** @var Service[] */
    protected array $service = [];

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
            throw new TrackingDataNotValidException('No client ID is set for GA client.');
        }

        $this->getRequest()->setClientId($data->getClientId()); // '2133506694.1448249699'

        $this->getRequest()->setTimestampMicros($this->getMicroTime());

        if ($data->getUserId()) {
            $this->getRequest()->setUserId((string) $data->getUserId()); // magento customer_id
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
            ->setIndex((float)$data->getPosition())
            ->setPrice((float)$data->getPrice())
            ->setQuantity((float)$data->getQuantity());

        $this->getPurchaseEvent()->addItem($itemParameter);
    }

    public function addUserDataItems($userDataItems){
        foreach ($userDataItems as $key => $userDataItem) {
            $this->addUserDataItem($key, $userDataItem);
        }
    }

    public function addUserDataItem($key, $userDataItem){
        if($key === 'address'){
            foreach($userDataItem as $addressData){
                $userAddress = new UserAddress();

                foreach($addressData as $addressKey => $addressValue){
                    $addressDataItem = new UserDataItem($addressKey, $addressValue);

                    $userAddress->addUserAddressItem($addressDataItem);
                }

                $this->getRequest()->getUserData()->addUserAddress($userAddress);
            }
        }else{
            $userDataItem = new UserDataItem($key, $userDataItem);

            $this->getRequest()->addUserDataItem($userDataItem);
        }
    }

    /**
     * @throws LocalizedException
     */
    public function firePurchaseEvent(int|string|null $storeId)
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

        $service = $this->getService($storeId);
        $response = $this->getService($storeId)->$send($baseRequest);

        $this->createLog(
            "Request: ",
            [
                "storeId" => $storeId,
                "measurementId" => $service->getMeasurementId(),
                "body" => $this->getRequest()->export()
            ]
        );
        $this->createLog('Response: ', [$response->getStatusCode()]);
    }

    public function getService(int|string|null $storeId)
    {
        $storeId = $this->moduleConfiguration->ensureStoreId($storeId);
        if (isset($this->service[$storeId])) {
            return $this->service[$storeId];
        }

        $this->service[$storeId] = new Service($this->moduleConfiguration->getApiSecret($storeId));
        $this->service[$storeId]->setMeasurementId($this->moduleConfiguration->getMeasurementId($storeId));

        return $this->service[$storeId];
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
