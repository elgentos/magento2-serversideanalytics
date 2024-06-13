<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Model\Resolver;

use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\Collection;
use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrder;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\Context;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GAResolver implements ResolverInterface
{
    public function __construct(
        protected CartRepositoryInterface $quoteRepository,
        protected MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        protected CollectionFactory $elgentosSalesOrderCollectionFactory,
        protected SalesOrderRepository $elgentosSalesOrderRepository,
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $input = $args['input'];

        /** @var Context $context */
        if (is_numeric($input['cartId']) && false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $cartId = is_numeric($input['cartId'])
            ? $input['cartId']
            : $this->maskedQuoteIdToQuoteId->execute($input['cartId']);

        /** @var Collection $elgentosSalesOrderCollection */
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        /** @var SalesOrder $elgentosSalesOrder */
        $elgentosSalesOrder = $elgentosSalesOrderCollection
            ->addFieldToFilter('quote_id', $cartId)
            ->getFirstItem();

        $elgentosSalesOrder->setQuoteId($cartId);
        if ($elgentosSalesOrder->getGaUserId() !== ($input['gaUserId'] ?? null)) {
            $elgentosSalesOrder->setGaUserId($input['gaUserId'] ?? '');
        }

        if ($elgentosSalesOrder->getGaSessionId() !== ($input['gaSessionId'] ?? null)) {
            $elgentosSalesOrder->setGaSessionId($input['gaSessionId'] ?? '');
        }

        $this->elgentosSalesOrderRepository->save($elgentosSalesOrder);

        return [
            'cartId' => $cartId,
            'maskedId' => !is_numeric($input['cartId']) ? $input['cartId'] : null
        ];
    }
}
