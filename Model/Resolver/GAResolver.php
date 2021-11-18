<?php

namespace Elgentos\ServerSideAnalytics\Model\Resolver;

use Exception;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;

class GAResolver implements ResolverInterface
{
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    protected $maskedQuoteIdToQuoteId;

    /**
     * Construct.
     * @param CartRepositoryInterface $quoteFactory
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    public function __construct(
        CartRepositoryInterface $quoteRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
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
        $quote = $this->quoteRepository->get($cartId);

        if ($quote->getData('ga_user_id') !== $args['gaUserId']) {
            $quote->setData('ga_user_id', $args['gaUserId'])->save();
        }

        return [
            'cartId' => $cartId,
            'maskedId' => !is_numeric($args['cartId']) ? $args['cartId'] : null
        ];
    }
}
