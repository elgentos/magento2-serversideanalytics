<?php

/**
 * Copyright Elgentos BV. All rights reserved.
 * https://www.elgentos.nl/
 */

declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Observer;

use Elgentos\ServerSideAnalytics\Config\ModuleConfiguration;
use Elgentos\ServerSideAnalytics\Model\SendPurchaseEvent;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;

class AfterOrderPayed implements ObserverInterface
{
    public function __construct(
        protected readonly SendPurchaseEvent $sendPurchaseEvent,
        protected readonly ModuleConfiguration $moduleConfiguration,
    ) {
    }

    public function execute(Observer $observer)
    {
        /** @var Payment $payment */
        $payment = $observer->getPayment();
        $method = $payment->getMethodInstance();

        /** @var Order $order */
        $order = $payment->getOrder();

        if ($this->moduleConfiguration->shouldTriggerOnPayment(paymentMethodCode: $method->getCode())) {
            $this->sendPurchaseEvent->execute($order, 'AfterOrderPayed');
        }
    }
}
