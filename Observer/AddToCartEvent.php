<?php
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Observer;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use Elgentos\ServerSideAnalytics\Validator\CanCommunicateWithGoogleValidator;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class AddToCartEvent implements ObserverInterface
{
    private GAClient $gaClient;
    private CanCommunicateWithGoogleValidator $canCommunicateWithGoogleValidator;

    public function __construct(
        CanCommunicateWithGoogleValidator $canCommunicateWithGoogleValidator,
        GAClient $gaClient
    ) {
        $this->canCommunicateWithGoogleValidator = $canCommunicateWithGoogleValidator;
        $this->gaClient = $gaClient;
    }

    public function execute(Observer $observer): void
    {
        if (!$this->canCommunicateWithGoogleValidator->execute()) {
            return;
        }

    }
}
