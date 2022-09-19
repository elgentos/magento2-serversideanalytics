<?php
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Validator;

use Elgentos\ServerSideAnalytics\Config\GaConfig;

class CanCommunicateWithGoogleValidator
{
    private GaConfig $gaConfig;

    public function __construct(GaConfig $gaConfig)
    {
        $this->gaConfig = $gaConfig;
    }

    public function execute(): bool
    {
        return $this->gaConfig->isEnabled() && $this->gaConfig->googleIdIsValid();
    }
}
