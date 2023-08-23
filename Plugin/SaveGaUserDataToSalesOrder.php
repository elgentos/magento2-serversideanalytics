<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Plugin;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\SalesOrderFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class SaveGaUserDataToSalesOrder
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly SalesOrderFactory $elgentosSalesOrderFactory,
        private readonly SalesOrderRepository $elgentosSalesOrderRepository,
        private readonly CookieManagerInterface $cookieManager,
        private readonly GAClient $gaclient
    ) {
    }

    public function afterPlace(
        OrderManagementInterface $subject,
        OrderInterface $result,
        OrderInterface $order
    ): OrderInterface {
        $jusit = 'hoi';

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE)
        ) {
            return $result;
        }

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_API_SECRET, ScopeInterface::SCOPE_STORE)
        ) {
            $this->logger->info('No Google Analytics secret has been found in the ServerSideAnalytics configuration.');
            return $result;
        }

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID, ScopeInterface::SCOPE_STORE)
        ) {
            $this->logger->info('No Google Analytics Measurement ID has been found in the ServerSideAnalytics configuration.');
            return $result;
        }

        $order = $result;
        $gaUserId = $this->getUserIdFromCookie();
        $gaSessionId = $this->getSessionIdFromCookie();

        if ($gaUserId === null) {
            $gaCookieUserId = random_int((int)1E8, (int)1E9);
            $gaCookieTimestamp = time();
            $gaUserId = implode('.', [$gaCookieUserId, $gaCookieTimestamp]);
            $this->logger->info('Google Analytics cookie not found, generated temporary value: ' . $gaUserId);
        }

        $elgentosSalesOrderData = $this->elgentosSalesOrderFactory->create();

        $elgentosSalesOrderData->setData('order_id', $order->getId());
        $elgentosSalesOrderData->setData('ga_user_id', $gaUserId);
        $elgentosSalesOrderData->setData('ga_session_id', $gaSessionId);

        $this->elgentosSalesOrderRepository->save($elgentosSalesOrderData);

        return $result;
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

        if (
            $gaCookieVersion != 'GA' . $this->gaclient->getVersion()
        ) {
            $this->logger->info('Google Analytics cookie version differs from Measurement Protocol API version; please upgrade.');
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
