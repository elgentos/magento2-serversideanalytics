<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Config;

class ModuleConfiguration extends AbstractConfigProvider
{
    public const XPATH_ENABLED                    = 'google/serverside_analytics/ga_enabled';
    public const XPATH_API_SECRET                 = 'google/serverside_analytics/api_secret';
    public const XPATH_MEASUREMENT_ID             = 'google/serverside_analytics/measurement_id';
    public const XPATH_CURRENCY_SOURCE            = 'google/serverside_analytics/currency_source';
    public const XPATH_DEBUG_MODE                 = 'google/serverside_analytics/debug_mode';
    public const XPATH_ENABLE_LOGGING             = 'google/serverside_analytics/enable_logging';
    public const XPATH_FALLBACK_GENERATION_MODE   = 'google/serverside_analytics/fallback_session_id_generation_mode';
    public const XPATH_FALLBACK_SESSION_ID_PREFIX = 'google/serverside_analytics/fallback_session_id_prefix';
    public const XPATH_FALLBACK_SESSION_ID        = 'google/serverside_analytics/fallback_session_id';
    public const XPATH_TAX_DISPLAY_TYPE           = 'tax/display/type';

    public function isDebugMode(int|null|string $store = null): bool
    {
        return $this->isSetFlag(static::XPATH_DEBUG_MODE, $store);
    }

    public function isLogging(int|null|string $store = null): bool
    {
        return $this->isSetFlag(static::XPATH_ENABLE_LOGGING, $store);
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
}
