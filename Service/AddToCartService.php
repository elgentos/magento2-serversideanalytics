<?php
declare(strict_types=1);

namespace Elgentos\ServerSideAnalytics\Service;

use Elgentos\ServerSideAnalytics\Model\GAClient;
use TheIconic\Tracking\GoogleAnalytics\Analytics;

class AddToCartService
{
    private GAClient $gaClient;

    public function __construct(GAClient $gaClient)
    {
        $this->gaClient = $gaClient;
    }

    public function execute()
    {
        $this->gaClient->
    }

}
