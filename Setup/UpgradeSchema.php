<?php

namespace Elgentos\ServerSideAnalytics\Setup;

use Magento\Framework\Setup\UpgradeSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class UpgradeSchema implements UpgradeSchemaInterface
{
    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $installer = $setup;
        $installer->startSetup();
        $connection = $installer->getConnection();

        if ($connection->tableColumnExists('sales_order', 'ga_user_id') === false) {
            $connection->addColumn(
                    $setup->getTable('sales_order'),
                    'ga_user_id',
                    [
                        'type' => Table::TYPE_TEXT,
                        'nullable'  => true,
                        'length'    => 255,
                        'after'     => null,
                        'comment'   => 'Google Analytics User ID for Server Side Analytics'
                    ]
                );
        }
        $installer->endSetup();
    }
}