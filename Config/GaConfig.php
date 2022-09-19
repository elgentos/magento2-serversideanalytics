<?php
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Config;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class GaConfig
{
    private ScopeConfigInterface $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    public function googleIdIsValid(): bool
    {
        return (bool)$this->scopeConfig->getValue(GAClient::GOOGLE_ANALYTICS_SERVERSIDE_UA, ScopeInterface::SCOPE_STORE);
    }
}
