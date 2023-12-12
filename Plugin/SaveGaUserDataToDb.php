<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Plugin;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Elgentos\ServerSideAnalytics\Logger\Logger;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\CartRepositoryInterface;

class SaveGaUserDataToDb
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Logger $logger,
        private readonly SalesOrderFactory $elgentosSalesOrderFactory,
        private readonly CollectionFactory $elgentosSalesOrderCollectionFactory,
        private readonly SalesOrderRepository $elgentosSalesOrderRepository,
        private readonly CookieManagerInterface $cookieManager,
        private readonly GAClient $gaclient
    ) {
    }

    public function afterSave(
        CartRepositoryInterface $subject,
        $result,
        $quote
    ) {
        if (!$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE)
        ) {
            $this->gaclient->createLog('Google ServerSideAnalytics is disabled in the config.');
            return $result;
        }

        if (!$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_API_SECRET, ScopeInterface::SCOPE_STORE)
        ) {
            $this->gaclient->createLog('No Google Analytics secret has been found in the ServerSideAnalytics configuration.');
            return $result;
        }

        if (!$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID, ScopeInterface::SCOPE_STORE)
        ) {
            $this->gaclient->createLog('No Google Analytics Measurement ID has been found in the ServerSideAnalytics configuration.');
            return $result;
        }

        /** @var Collection $elgentosSalesOrderCollection */
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        $elgentosSalesOrderData = $elgentosSalesOrderCollection
            ->addFieldToFilter('quote_id', $quote->getId())
            ->getFirstItem();

        if ($this->getUserIdFromCookie() === $elgentosSalesOrderData->getGaUserId()) {
            return;
        }

        $elgentosSalesOrderData->setData('quote_id', $quote->getId());
        $elgentosSalesOrderData->setData('ga_user_id', $this->getGaUserId());
        $elgentosSalesOrderData->setData('ga_session_id', $this->getGaSessionId());

        try {
            $this->elgentosSalesOrderRepository->save($elgentosSalesOrderData);
        } catch (\Exception $exception) {
            $this->gaclient->createLog($exception->getMessage());
        }

        return;
    }

    protected function getGaUserId() {
        $gaUserId = $this->getUserIdFromCookie();

        if ($gaUserId === null) {
            $gaCookieUserId = random_int((int)1E8, (int)1E9);
            $gaCookieTimestamp = time();
            $gaUserId = implode('.', [$gaCookieUserId, $gaCookieTimestamp]);
            $this->gaclient->createLog('Google Analytics cookie not found, generated temporary GA User Id: ' . $gaUserId);
        }

        return $gaUserId;
    }

    protected  function getGaSessionId() {

        $gaSessionId = $this->getSessionIdFromCookie();

        if ($gaSessionId) {
            return $gaSessionId;
        }

        if ($this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_FALLBACK_SESSION_ID_GENERATIONMODE,
                ScopeInterface::SCOPE_STORE)
            === '1'
        ) {
            return $this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_FALLBACK_SESSION_ID, ScopeInterface::SCOPE_STORE) ?? '9999999999999';
        }

        if ($this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_FALLBACK_SESSION_ID_GENERATIONMODE,
                ScopeInterface::SCOPE_STORE)
            === '3'
        ) {
            $prefix = $this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_FALLBACK_SESSION_ID_PREFIX, ScopeInterface::SCOPE_STORE);
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

        if (empty($gaCookie) || count($gaCookie) < 4) {
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

        if ($gaCookieVersion != 'GA' . $this->gaclient->getVersion()
        ) {
            $this->gaclient->createLog('Google Analytics cookie version differs from Measurement Protocol API version; please upgrade.');
            return null;
        }

        return implode('.', [$gaCookieUserId, $gaCookieTimestamp]);
    }

    protected function getSessionIdFromCookie()
    {
        $gaMeasurementId = $this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID, ScopeInterface::SCOPE_STORE);
        $gaMeasurementId = str_replace('G-', '', $gaMeasurementId);

        $gaCookie = explode('.', $this->cookieManager->getCookie('_ga_' . $gaMeasurementId) ?? '');

        if (empty($gaCookie) || count($gaCookie) < 9) {
            return null;
        }

        return $gaCookie[2];
    }
}
