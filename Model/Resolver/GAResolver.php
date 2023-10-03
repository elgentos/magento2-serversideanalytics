<?php

namespace Elgentos\ServerSideAnalytics\Model\Resolver;

use Elgentos\ServerSideAnalytics\Model\ResourceModel\SalesOrder\CollectionFactory;
use Elgentos\ServerSideAnalytics\Model\SalesOrderRepository;
use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
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
        $this->quoteRepository = $quoteRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        if (is_numeric($args['cartId']) && false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $cartId = is_numeric($args['cartId'])
            ? $args['cartId']
            : $this->maskedQuoteIdToQuoteId->execute($args['cartId']);

        /** @var Collection $elgentosSalesOrder */
        $elgentosSalesOrderCollection = $this->elgentosSalesOrderCollectionFactory->create();
        $elgentosSalesOrder = $elgentosSalesOrderCollection
            ->addFieldToFilter('quote_id', $cartId)
            ->getFirstItem();

        $elgentosSalesOrder->setQuoteId($cartId);
        if ($elgentosSalesOrder->getGaUserId() !== ($args['gaUserId'] ?? null)) {
            $elgentosSalesOrder->setGaUserId($args['gaUserId']);
        }

        if ($elgentosSalesOrder->getGaSessionId() !== ($args['gaSessionId'] ?? null)) {
            $elgentosSalesOrder->setGaSessionId($args['gaSessionId']);
        }

        $this->elgentosSalesOrderRepository->save($elgentosSalesOrder);

        return [
            'cartId' => $cartId,
            'maskedId' => !is_numeric($args['cartId']) ? $args['cartId'] : null
        ];
    }
}
