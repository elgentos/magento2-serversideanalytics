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
    public const XPATH_ENABLED                    = 'google/serverside_analytics/enable';
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

    public function isDebugMode(int|null|string $store = null): bool
    {
        return $this->isSetFlag(static::XPATH_DEBUG_MODE, $store);
    }

    public function isLogging(int|null|string $store = null): bool
    {
        return $this->isSetFlag(static::XPATH_ENABLE_LOGGING, $store);
    }

    public function getCurrencySource(int|null|string $store = null): ?int
    {
        return $this->getConfigAsInt(static::XPATH_CURRENCY_SOURCE, $store);
    }

    public function getTaxDisplayType(int|null|string $store = null): ?int
    {
        return $this->getConfigAsInt(static::XPATH_TAX_DISPLAY_TYPE, $store);
    }

    public function getFallbackGenerationMode(int|null|string $store = null): ?int
    {
        return $this->getConfigAsInt(static::XPATH_FALLBACK_GENERATION_MODE, $store);
    }

    public function getFallbackSessionIdPrefix(int|null|string $store = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_FALLBACK_SESSION_ID_PREFIX, $store);
    }

    public function getFallbackSessionId(int|null|string $store = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_FALLBACK_SESSION_ID, $store);
    }

    public function shouldTriggerOnPayment(int|null|string $store = null, ?string $paymentMethodCode = null): ?bool
    {
        return $this->shouldTriggerOn($store, TriggerMode::PAYED, $paymentMethodCode);
    }

    protected function shouldTriggerOn(
        int|null|string $store = null,
        int $mode,
        ?string $paymentMethodCode = null
    ): ?bool {
        return $this->isReadyForUse() && $this->shouldTriggerOnMode($store, $mode, $paymentMethodCode);
    }

    public function isReadyForUse(int|null|string $store = null): bool
    {
        return $this->isEnabled($store) && !empty($this->getApiSecret()) && !empty($this->getMeasurementId());
    }

    public function isEnabled(int|null|string $store = null): bool
    {
        return $this->isSetFlag(static::XPATH_ENABLED, $store);
    }

    public function getApiSecret(int|null|string $store = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_API_SECRET, $store);
    }

    public function getMeasurementId(int|null|string $store = null): ?string
    {
        return $this->getConfigAsString(static::XPATH_MEASUREMENT_ID, $store);
    }

    protected function shouldTriggerOnMode(
        int|null|string $store = null,
        int $mode,
        ?string $paymentMethodCode = null
    ): ?bool {
        $triggerMode = $this->getTriggerMode($store);
        if ($triggerMode === $mode) {
            return true;
        }

        if ($triggerMode !== TriggerMode::PAYMENT_METHOD_DEPENDENT) {
            return false;
        }

        if (!$paymentMethodCode) {
            return false;
        }

        if (in_array($paymentMethodCode, $this->getPaymentMethodsForTrigger($store, $mode))) {
            return true;
        }

        return true;
    }

    public function getTriggerMode(int|null|string $store = null): ?int
    {
        $value = $this->getConfigAsInt(static::XPATH_TRIGGER_MODE, $store);
        if (in_array($value, TriggerMode::ALL_OPTION_VALUES)) {
            return $value;
        }

        return null;
    }

    public function getPaymentMethodsForTrigger(int|null|string $store = null, int $mode): array
    {
        switch ($mode) {
            case TriggerMode::PLACED:
                return explode(
                    ',',
                    $this->getConfigAsString(self::XPATH_TRIGGER_ON_PLACED_METHODS, $store)
                );
            case TriggerMode::PAYED:
                return explode(
                    ',',
                    $this->getConfigAsString(self::XPATH_TRIGGER_ON_PAYED_METHODS, $store)
                );
        }

        return [];
    }

    public function shouldTriggerOnPlaced(int|null|string $store = null, ?string $paymentMethodCode = null): ?bool
    {
        return $this->shouldTriggerOn($store, TriggerMode::PLACED, $paymentMethodCode);
    }
}
