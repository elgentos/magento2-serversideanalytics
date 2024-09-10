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

    public function isSetFlag(string $xpath, int|null|string $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            $xpath,
            ScopeInterface::SCOPE_STORE,
            scopeCode: $storeId
        );
    }

    protected function getConfig(string $xpath, int|null|string $storeId = null): mixed
    {
        return $this->scopeConfig->getValue(
            $xpath,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getConfigAsString(string $xpath, int|null|string $storeId = null): ?string
    {
        return (string)$this->getConfig($xpath, $storeId);
    }

    public function getConfigAsInt(string $xpath, int|null|string $storeId = null): int
    {
        return (int)$this->getConfig($xpath, $storeId);
    }
}
