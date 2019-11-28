<?php

namespace Elgentos\ServerSideAnalytics\Observer;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\ScopeInterface;

class SaveGaUserId implements ObserverInterface
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
     * @var \Magento\Framework\Stdlib\CookieManagerInterface
     */
    private $cookieManager;
    /**
     * @var GAClient
     */
    private $gaclient;

    /**
     * SaveGaUserId constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param GAClient $gaclient
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
                                \Psr\Log\LoggerInterface $logger,
                                \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager,
                                GAClient $gaclient)
    {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->cookieManager = $cookieManager;
        $this->gaclient = $gaclient;
    }

    /**
     * When Order object is saved add the GA User Id if available in the cookies.
     *
     * @param \Magento\Framework\Event\Observer $observer
     */

    public function execute(Observer $observer)
    {
        if (!$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE)) {
            return;
        }

        if (!$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_UA, ScopeInterface::SCOPE_STORE)) {
            $this->logger->info('No Google Analytics account number has been found in the ServerSideAnalytics configuration.');
            return;
        }

        $order = $observer->getEvent()->getOrder();

        $gaCookie = explode('.', $this->cookieManager->getCookie('_ga'));

        if (empty($gaCookie) || count($gaCookie) < 4) {
            return;
        }

        list(
            $gaCookieVersion,
            $gaCookieDomainComponents,
            $gaCookieUserId,
            $gaCookieTimestamp
            ) = $gaCookie;

        if (!$gaCookieUserId || !$gaCookieTimestamp) {
            return;
        }

        $client = $this->gaclient;

        if ($gaCookieVersion != 'GA' . $client->getVersion()) {
            $this->logger->info('Google Analytics cookie version differs from Measurement Protocol API version; please upgrade.');
            return;
        }

        $gaUserId = implode('.', [$gaCookieUserId, $gaCookieTimestamp]);

        $order->setData('ga_user_id', $gaUserId);
    }

}