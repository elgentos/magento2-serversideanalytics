<?php

namespace Elgentos\ServerSideAnalytics\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Website;

class RenameConfigPaths implements DataPatchInterface
{
    public function __construct(
        protected ModuleDataSetupInterface $moduleDataSetup,
        protected WriterInterface $configWriter,
        protected StoreManagerInterface $storeManager,
        protected ScopeConfigInterface $scopeConfig
    ) {
    }

    public function apply()
    {
        $this->moduleDataSetup->startSetup();
        $configPathMap = [
            'google/serverside_analytics/ga_enabled' =>
                'google/serverside_analytics/enabled',
            'google/serverside_analytics/api_secret' =>
                'google/serverside_analytics/general/api_secret',
            'google/serverside_analytics/measurement_id' =>
                'google/serverside_analytics/general/measurement_id',
            'google/serverside_analytics/currency_source' =>
                'google/serverside_analytics/general/currency_source',
            'google/serverside_analytics/fallback_session_id_generation_mode' =>
                'google/serverside_analytics/fallback_session_id/mode',
            'google/serverside_analytics/fallback_session_id' =>
                'google/serverside_analytics/fallback_session_id/id',
            'google/serverside_analytics/fallback_session_id_prefix' =>
                'google/serverside_analytics/fallback_session_id/prefix',
            'google/serverside_analytics/debug_mode' =>
                'google/serverside_analytics/developer/debug',
            'google/serverside_analytics/enable_logging' =>
                'google/serverside_analytics/developer/logging',
        ];
        foreach ($configPathMap as $old => $new) {
            $this->renameConfigPath($old, $new);
        }

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    protected function renameConfigPath(string $oldPath, string $newPath): void
    {
        // Get default config
        $defaultValue = $this->renameScopedConfigPath(
            $oldPath,
            $newPath,
            null
        );

        // Get website config
        $websites = $this->storeManager->getWebsites();
        /** @var Website $website */
        foreach ($websites as $website) {
            $websiteValue = $this->renameScopedConfigPath(
                $oldPath,
                $newPath,
                $defaultValue,
                ScopeInterface::SCOPE_WEBSITES,
                $website->getId()
            );

            // Get store views config
            $stores = $website->getStores();
            foreach ($stores as $store) {
                $this->renameScopedConfigPath(
                    $oldPath,
                    $newPath,
                    $websiteValue,
                    ScopeInterface::SCOPE_STORES,
                    $store->getId()
                );
            }
        }
    }

    protected function renameScopedConfigPath(
        string $oldPath,
        string $newPath,
        mixed $parentValue,
        string $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        int $scopeId = 0
    ): mixed {
        $value = $this->scopeConfig->getValue($oldPath, $scopeType, $scopeId);
        $this->configWriter->delete($oldPath, $scopeType, $scopeId);
        if ($value !== $parentValue) {
            $parentValue = $value;
            $this->configWriter->save($newPath, $value, $scopeType, $scopeId);
        }

        return $parentValue;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }
}
