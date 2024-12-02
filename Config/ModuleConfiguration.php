<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Config;

use Elgentos\ServerSideAnalytics\Model\Source\TriggerMode;

class ModuleConfiguration extends AbstractConfigProvider
{
    public const XPATH_ENABLED                    = 'google/serverside_analytics/enabled';
    public const XPATH_API_SECRET                 = 'google/serverside_analytics/general/api_secret';
    public const XPATH_MEASUREMENT_ID             = 'google/serverside_analytics/general/measurement_id';
    public const XPATH_CURRENCY_SOURCE            = 'google/serverside_analytics/general/currency_source';
    public const XPATH_TRIGGER_MODE               = 'google/serverside_analytics/trigger_on/mode';
    public const XPATH_TRIGGER_ON_PLACED_METHODS  = 'google/serverside_analytics/trigger_on/on_placed_methods';
    public const XPATH_TRIGGER_ON_PAYED_METHODS   = 'google/serverside_analytics/trigger_on/on_payed_methods';
    public const XPATH_FALLBACK_GENERATION_MODE   = 'google/serverside_analytics/fallback_session_id/mode';
    public const XPATH_FALLBACK_SESSION_ID_PREFIX = 'google/serverside_analytics/fallback_session_id/prefix';
    public const XPATH_FALLBACK_SESSION_ID        = 'google/serverside_analytics/fallback_session_id/id';
    public const XPATH_TAX_DISPLAY_TYPE           = 'tax/display/type';
    public const XPATH_DEBUG_MODE                 = 'google/serverside_analytics/developer/debug';
    public const XPATH_ENABLE_LOGGING             = 'google/serverside_analytics/developer/logging';

    public function ensureStoreId(int|null|string $storeId = null): int|string
    {
        return $this->storeManager->getStore($storeId)->getId();
    }

    public function isDebugMode(int|null|string $storeId = null): bool
    {
        return $this->isSetFlag(static::XPATH_DEBUG_MODE, $storeId);
    }

    public function isLogging(int|null|string $storeId = null): bool
    {
        return $this->isSetFlag(static::XPATH_ENABLE_LOGGING, $storeId);
    }

    public function getCurrencySource(int|null|string $storeId = null): ?int
    {
        return $this->getConfigAsInt(static::XPATH_CURRENCY_SOURCE, $storeId);
    }

    public function getTaxDisplayType(int|null|string $storeId = null): ?int
    {
        return $this->getConfigAsInt(static::XPATH_TAX_DISPLAY_TYPE, $storeId);
    }

    public function getFallbackGenerationMode(int|null|string $storeId = null): ?int
    {
        return $this->getConfigAsInt(static::XPATH_FALLBACK_GENERATION_MODE, $storeId);
    }

    public function getFallbackSessionIdPrefix(int|null|string $storeId = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_FALLBACK_SESSION_ID_PREFIX, $storeId);
    }

    public function getFallbackSessionId(int|null|string $storeId = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_FALLBACK_SESSION_ID, $storeId);
    }

    public function shouldTriggerOnPayment(int|null|string $storeId = null, ?string $paymentMethodCode = null): ?bool
    {
        return $this->shouldTriggerOn(
            mode: TriggerMode::PAYED,
            storeId: $storeId,
            paymentMethodCode: $paymentMethodCode
        );
    }

    protected function shouldTriggerOn(
        int $mode,
        int|null|string $storeId = null,
        ?string $paymentMethodCode = null
    ): ?bool {
        return $this->isReadyForUse($storeId) && $this->shouldTriggerOnMode(
            mode: $mode,
            storeId: $storeId,
            paymentMethodCode: $paymentMethodCode
        );
    }

    public function isReadyForUse(int|null|string $storeId = null): bool
    {
        return $this->isEnabled($storeId) &&
            !empty($this->getApiSecret($storeId)) &&
            !empty($this->getMeasurementId($storeId));
    }

    public function isEnabled(int|null|string $storeId = null): bool
    {
        return $this->isSetFlag(static::XPATH_ENABLED, $storeId);
    }

    public function getApiSecret(int|null|string $storeId = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_API_SECRET, $storeId);
    }

    public function getMeasurementId(int|null|string $storeId = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_MEASUREMENT_ID, $storeId);
    }

    protected function shouldTriggerOnMode(
        int $mode,
        int|null|string $storeId = null,
        ?string $paymentMethodCode = null
    ): ?bool {
        $triggerMode = $this->getTriggerMode($storeId);
        if ($triggerMode === $mode) {
            return true;
        }

        if ($triggerMode !== TriggerMode::PAYMENT_METHOD_DEPENDENT) {
            return false;
        }

        if (!$paymentMethodCode) {
            return false;
        }

        if (in_array($paymentMethodCode, $this->getPaymentMethodsForTrigger(mode: $mode, storeId: $storeId))) {
            return true;
        }

        return false;
    }

    public function getTriggerMode(int|null|string $storeId = null): ?int
    {
        $value = $this->getConfigAsInt(static::XPATH_TRIGGER_MODE, $storeId);
        if (in_array($value, TriggerMode::ALL_OPTION_VALUES)) {
            return $value;
        }

        return null;
    }

    public function getPaymentMethodsForTrigger(int $mode = null, int|null|string $storeId = null): array
    {
        switch ($mode) {
            case TriggerMode::PLACED:
                return explode(
                    ',',
                    $this->getConfigAsString(self::XPATH_TRIGGER_ON_PLACED_METHODS, $storeId)
                );
            case TriggerMode::PAYED:
                return explode(
                    ',',
                    $this->getConfigAsString(self::XPATH_TRIGGER_ON_PAYED_METHODS, $storeId)
                );
        }

        return [];
    }

    public function shouldTriggerOnPlaced(int|null|string $storeId = null, ?string $paymentMethodCode = null): ?bool
    {
        return $this->shouldTriggerOn(
            mode: TriggerMode::PLACED,
            storeId: $storeId,
            paymentMethodCode: $paymentMethodCode
        );
    }
}
