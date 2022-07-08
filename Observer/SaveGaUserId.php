<?php

namespace Elgentos\ServerSideAnalytics\Observer;

use Psr\Log\LoggerInterface;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\UAClient;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class SaveGaUserId implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CookieManagerInterface
     */
    private $cookieManager;

    /**
     * @var GAClient
     */
    private $gaclient;

    /**
     * @var UAClient
     */
    private $uaclient;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * SaveGaUserId constructor.
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Stdlib\CookieManagerInterface $cookieManager
     * @param GAClient $gaclient
     * @param UAClient $uaclient
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        CookieManagerInterface $cookieManager,
        GAClient $gaclient,
        UAClient $uaclient,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->cookieManager = $cookieManager;
        $this->gaclient = $gaclient;
        $this->uaclient = $uaclient;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * When Order object is saved add the GA User Id if available in the cookies.
     *
     * @param Observer $observer
     */

    public function execute(Observer $observer)
    {
        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE) &&
            !$this->scopeConfig->getValue(UAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE)
        ) {
            return;
        }

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_API_SECRET, ScopeInterface::SCOPE_STORE) &&
            !$this->scopeConfig->getValue(UAClient::GOOGLE_ANALYTICS_SERVERSIDE_UA, ScopeInterface::SCOPE_STORE)
        ) {
            $this->logger->info('No Google Analytics secret or UA has been found in the ServerSideAnalytics configuration.');
            return;
        }

        $order = $observer->getEvent()->getOrder();
        $quote = $this->quoteRepository->get($order->getQuoteId());
        $gaUserId = $quote->getData('ga_user_id') ?: $this->getUserIdFromCookie();
        if ($gaUserId === null) {
            $gaCookieUserId = random_int(1E8, 1E9);
            $gaCookieTimestamp = time();
            $gaUserId = implode('.', [$gaCookieUserId, $gaCookieTimestamp]);
            $this->logger->info('Google Analytics cookie not found, generated temporary value: ' . $gaUserId);
        }

        $order->setData('ga_user_id', $gaUserId);
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

        list(
            $gaCookieVersion,
            $gaCookieDomainComponents,
            $gaCookieUserId,
            $gaCookieTimestamp
            ) = $gaCookie;

        if (!$gaCookieUserId || !$gaCookieTimestamp) {
            return null;
        }

        if (
            $gaCookieVersion != 'GA' . $this->gaclient->getVersion() &&
            $gaCookieVersion != 'UA' . $this->uaclient->getVersion()
        ) {
            $this->logger->info('Google Analytics cookie version differs from Measurement Protocol API version; please upgrade.');
            return null;
        }

        return implode('.', [$gaCookieUserId, $gaCookieTimestamp]);
    }
}
