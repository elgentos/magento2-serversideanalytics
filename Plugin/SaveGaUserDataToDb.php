<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Plugin;

use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\Collection;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Elgentos\ServerSideAnalytics\Model\Source\Fallback;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class SaveGaUserDataToDb
{
    public function __construct(
        protected readonly ModuleConfiguration $moduleConfiguration,
        protected readonly CollectionFactory $elgentosSalesOrderCollectionFactory,
        protected readonly SalesOrderRepository $elgentosSalesOrderRepository,
        protected readonly CookieManagerInterface $cookieManager,
        protected readonly GAClient $gaclient
    ) {
    }

    public function afterSave(
        CartRepositoryInterface $subject,
        $result,
        $quote
    ) {
        if (!$this->moduleConfiguration->isReadyForUse($quote->getStoreId())) {
            $this->gaclient->createLog(
                'Google ServerSideAnalytics is disabled or not configured check the ServerSideAnalytics configuration.'
            );
            return $result;
        }

        /** @var Collection $elgentosSalesOrderCollection */
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        $elgentosSalesOrderData = $elgentosSalesOrderCollection
            ->addFieldToFilter('quote_id', $quote->getId())
            ->getFirstItem();

        if ($this->getGaUserId() === $elgentosSalesOrderData->getGaUserId()) {
            return;
        }

        $elgentosSalesOrderData->setData('quote_id', $quote->getId());
        $elgentosSalesOrderData->setData('ga_user_id', $this->getGaUserId());
        $elgentosSalesOrderData->setData('ga_session_id', $this->getGaSessionId($quote->getStoreId()));

        try {
            $this->elgentosSalesOrderRepository->save($elgentosSalesOrderData);
        } catch (\Exception $exception) {
            $this->gaclient->createLog($exception->getMessage());
        }
    }

    protected function getGaUserId()
    {
        $gaUserId = $this->getUserIdFromCookie();

        if ($gaUserId === null) {
            $gaCookieUserId = random_int((int)1E8, (int)1E9);
            $gaCookieTimestamp = time();
            $gaUserId = implode('.', [$gaCookieUserId, $gaCookieTimestamp]);
            $this->gaclient->createLog(
                'Google Analytics cookie not found, generated temporary GA User Id: ' . $gaUserId
            );
        }

        return $gaUserId;
    }

    protected function getGaSessionId(int|null|string $storeId = null)
    {

        $gaSessionId = $this->getSessionIdFromCookie($storeId);

        if ($gaSessionId) {
            return $gaSessionId;
        }

        if ($this->moduleConfiguration->getFallbackGenerationMode($storeId) === Fallback::DEFAULT) {
            return $this->moduleConfiguration->getFallbackSessionId() ?? '9999999999999';
        }

        if ($this->moduleConfiguration->getFallbackGenerationMode($storeId) === Fallback::PREFIX) {
            $prefix = $this->moduleConfiguration->getFallbackSessionIdPrefix($storeId);
            if (!$prefix) {
                $prefix = '9999';
            }

            return $prefix . random_int((int)1E5, (int)1E6);
        }

        return random_int((int)1E5, (int)1E9);
    }

    /**
     * Try to get the Google Analytics User ID from the cookie
     *
     * @return string|null
     */
    protected function getUserIdFromCookie()
    {
        $gaCookie = explode('.', $this->cookieManager->getCookie('_ga') ?? '');

        if (count($gaCookie) < 4) {
            return null;
        }

        [
            $gaCookieVersion,
            $gaCookieDomainComponents,
            $gaCookieUserId,
            $gaCookieTimestamp
        ] = $gaCookie;

        if (!$gaCookieUserId || !$gaCookieTimestamp) {
            return null;
        }

        if (
            $gaCookieVersion != 'GA' . $this->gaclient->getVersion()
        ) {
            $this->gaclient->createLog(
                'Google Analytics cookie version differs from Measurement Protocol API version; please upgrade.'
            );
            return null;
        }

        return implode('.', [$gaCookieUserId, $gaCookieTimestamp]);
    }

    protected function getSessionIdFromCookie(int|null|string $storeId = null)
    {
        $gaMeasurementId = $this->moduleConfiguration->getMeasurementId($storeId);
        $gaMeasurementId = str_replace('G-', '', $gaMeasurementId);

        $gaCookie = explode(
            '.',
            $this->cookieManager->getCookie('_ga_' . $gaMeasurementId) ?? ''
        );

        if (count($gaCookie) < 9) {
            return null;
        }

        return $gaCookie[2];
    }
}
