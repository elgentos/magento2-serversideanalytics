<?php
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Setup;

use Elgentos\ServerSideAnalytics\Model\Source\CurrencySource;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class UpgradeData implements UpgradeDataInterface
{

    public function __construct(
        protected WriterInterface $configWriter
    ) {
    }

    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($context->getVersion() && version_compare($context->getVersion(), '1.3.0', '<')) {
            // Extension was already installed, and older than 1.3.0 set currency source to global as it was
            $this->configWriter->save(
                'google/serverside_analytics/currency_source',
                CurrencySource::GLOBAL
            );
        }

        $setup->endSetup();
    }
}
