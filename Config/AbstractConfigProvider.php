<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractConfigProvider
{
    public function __construct(
        protected ScopeConfigInterface  $scopeConfig,
        protected StoreManagerInterface $storeManager
    ) {
    }

    public function isSetFlag(string $xpath, int|null|string $store = null): bool
    {
        return $this->scopeConfig->isSetFlag($xpath, $store);
    }

    protected function getConfig(string $xpath, int|null|string $store = null): mixed
    {
        return $this->scopeConfig->getValue(
            $xpath,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    public function getConfigAsString(string $xpath, int|null|string $store = null): ?string
    {
        return (string)$this->getConfig($xpath, $store);
    }

    public function getConfigAsInt(string $xpath, int|null|string $store = null): int
    {
        return (int)$this->getConfig($xpath, $store);
    }
}
