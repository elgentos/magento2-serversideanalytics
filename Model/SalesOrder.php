<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model;

use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\Source\Fallback;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder as SalesOrderResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\CookieManagerInterface;

class SalesOrder extends AbstractModel
{
    public function __construct(
        Context $context,
        Registry $registry,
        SalesOrderResource $resource = null,
        AbstractDb $resourceCollection = null,
        protected readonly ModuleConfiguration $moduleConfiguration,
        protected readonly CollectionFactory $elgentosSalesOrderCollectionFactory,
        protected readonly SalesOrderRepository $elgentosSalesOrderRepository,
        protected readonly CookieManagerInterface $cookieManager,
        protected readonly GAClient $gaclient,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    protected function _construct(): void
    {
        $this->_init(ResourceModel\SalesOrder::class);
    }

    /**
     * Set the ga_user_id and ga_session_id on the current sales order.
     * Any missing data will be gathered from the cookies, or generated.
     */
    public function setGaData(int|null|string $storeId = null, null|string $gaUserId = null, int|null|string $gaSessionId = null): static
    {
        return $this->setData([
            'ga_user_id' => $gaUserId ?? $this->getCurrentGaUserId(),
            'ga_session_id' => $gaSessionId ?? $this->getCurrentGaSessionId($storeId),
        ]);
    }

    /**
     * Attemt to get the current GA User id from cookies, database or generating it.
     */
    public function getCurrentGaUserId(): string
    {
        $gaUserId = $this->getUserIdFromCookie($this->cookieManager->getCookie('_ga')) ?? $this->getGaUserId();

        if (!$gaUserId) {
            $gaUserId = $this->generateFallbackUserId();
            $this->gaclient->createLog(
                'Google Analytics cookie not found, generated temporary GA User Id: ' . $gaUserId
            );
        }

        return (string) $gaUserId;
    }

    /**
     * Attemt to get the current GA User id from cookies, database or generating it.
     */
    public function getCurrentGaSessionId(int|null|string $storeId = null): string
    {
        $measurementId = $this->moduleConfiguration->getMeasurementId($storeId);
        $gaSessionId = $this->getSessionIdFromCookie(
            $this->getSessionIdCookie($measurementId)
        );

        if ($gaSessionId) {
            return $gaSessionId;
        }

        return (string) ($this->getGaSessionId() ?? $this->generateFallbackSessionId(
            $this->moduleConfiguration->getFallbackGenerationMode($storeId),
            $storeId
        ));
    }

    /**
     * Try to get the Google Analytics User ID from the cookie
     */
    public function getUserIdFromCookie($gaCookie = '' /** GA1.1.99999999.1732012836 */): ?string
    {
        $gaCookie = explode('.', $gaCookie ?? '');

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

    /**
     * Generate a fallback analytics user id
     */
    public function generateFallbackUserId(): string
    {
        $gaCookieUserId = random_int((int)1E8, (int)1E9);
        $gaCookieTimestamp = time();
        $gaUserId = implode('.', [$gaCookieUserId, $gaCookieTimestamp]);

        return $gaUserId;
    }

    public function getSessionIdCookie($gaMeasurementId = '' /** G-0XX00XXX */ ): string
    {
        $gaMeasurementId = str_replace('G-', '', $gaMeasurementId ?? '');

        return $this->cookieManager->getCookie('_ga_' . $gaMeasurementId) ?? '';
    }

    public function getSessionIdFromCookie(?string $gaCookie = '' /** GS1.1.1732016998.2.1.1732018235.0.0.692404937 */): ?string
    {
        $gaCookie = explode(
            '.',
            $gaCookie ?? ''
        );

        if (count($gaCookie) < 9) {
            return null;
        }

        [
            $gaCookieVersion,
            $gaCookieDomainComponents,
            $gaCookieSessionStartTime,
            $gaCookieSessionsCount,
            $gaCookieEngagedSession,
            $gaCookieLastEventTime,
            $gaCookieCountdown,
            $unknownZero1,
            $unknownZero2,
            $gaCookieEngagedTime,
        ] = $gaCookie;

        return (string) $gaCookieSessionStartTime;
    }

    /**
     * Generate a fallback analytics session id
     */
    public function generateFallbackSessionId($fallbackGenerationMode = Fallback::DEFAULT, int|null|string $storeId = null): string
    {
        if ($fallbackGenerationMode === Fallback::DEFAULT) {
            return (string) $this->moduleConfiguration->getFallbackSessionId() ?? '9999999999999';
        }

        if ($fallbackGenerationMode === Fallback::PREFIX) {
            $prefix = $this->moduleConfiguration->getFallbackSessionIdPrefix($storeId);
            if (!$prefix) {
                $prefix = '9999';
            }

            return (string) $prefix . random_int((int)1E5, (int)1E6);
        }

        return (string) random_int((int)1E5, (int)1E9);
    }
}
