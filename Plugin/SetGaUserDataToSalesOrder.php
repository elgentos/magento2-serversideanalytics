<?php declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Plugin;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\Collection;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;

class SetGaUserDataToSalesOrder
{
    private CollectionFactory $elgentosSalesOrderCollectionFactory;

    /**
     * AddGaUserDataToSalesOrder constructor.
     * @param CollectionFactory $acmeSalesOrderCollectionFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger,
        CookieManagerInterface $cookieManager,
        GAClient $gaclient,
        CollectionFactory $elgentosSalesOrderCollectionFactory,
        \Elgentos\ServerSideAnalytics\Model\SalesOrderFactory $elgentosSalesOrderFactory,
        \Elgentos\ServerSideAnalytics\Model\SalesOrderRepository $elgentosSalesOrderRepository
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger = $logger;
        $this->cookieManager = $cookieManager;
        $this->gaclient = $gaclient;
        $this->elgentosSalesOrderFactory = $elgentosSalesOrderFactory;
        $this->elgentosSalesOrderRepository = $elgentosSalesOrderRepository;
    }

    /**
     * @param OrderRepositoryInterface $subject
     * @param $result
     * @return mixed
     */
    public function afterSave(
        OrderRepositoryInterface $subject,
        $result
    ) {

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE) &&
        ) {
            return;
        }

        if (
            !$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_API_SECRET, ScopeInterface::SCOPE_STORE) &&
        ) {
            $this->logger->info('No Google Analytics secret has been found in the ServerSideAnalytics configuration.');
            return;
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

        $this->elgentosSalesOrderRepository->save($data);

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
        $GaMeasurementId = $this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_MEASUREMENT_ID, ScopeInterface::SCOPE_STORE);
        $GaMeasurementId = str_replace('G-', '', $GaMeasurementId);

        $gaCookie = explode('.', $this->cookieManager->getCookie('_ga_' . $GaMeasurementId) ?? '');

        if (empty($gaCookie) || count($gaCookie) < 9) {
            return null;
        }

        return $gaCookie[2];
    }
}
