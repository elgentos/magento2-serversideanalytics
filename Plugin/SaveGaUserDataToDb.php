<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Plugin;

use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\Collection;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Magento\Quote\Api\CartRepositoryInterface;

class SaveGaUserDataToDb
{
    public function __construct(
        protected readonly ModuleConfiguration $moduleConfiguration,
        protected readonly CollectionFactory $elgentosSalesOrderCollectionFactory,
        protected readonly SalesOrderRepository $elgentosSalesOrderRepository,
        protected readonly GAClient $gaclient
    ) {
    }

    public function afterSave(
        CartRepositoryInterface $subject,
        $result,
        $quote
    ) {
        if (!$this->moduleConfiguration->isReadyForUse($quote->getStoreId())) {
            $this->gaclient->createLog(
                'Google ServerSideAnalytics is disabled or not configured check the ServerSideAnalytics configuration.'
            );
            return $result;
        }

        /** @var Collection $elgentosSalesOrderCollection */
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        /** @var SalesOrder $elgentosSalesOrderData */
        $elgentosSalesOrderData = $elgentosSalesOrderCollection
            ->addFieldToFilter('quote_id', $quote->getId())
            ->getFirstItem();

        if ($elgentosSalesOrderData->getCurrentGaUserId() === $elgentosSalesOrderData->getGaUserId()) {
            return;
        }

        $elgentosSalesOrderData->setData('quote_id', $quote->getId());
        $elgentosSalesOrderData->setGaData(storeId: $quote->getStoreId());

        try {
            $this->elgentosSalesOrderRepository->save($elgentosSalesOrderData);
        } catch (\Exception $exception) {
            $this->gaclient->createLog($exception->getMessage());
        }
    }
}
