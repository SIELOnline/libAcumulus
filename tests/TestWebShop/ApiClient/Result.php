<?php
namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\Helpers\Severity;

class Result extends \Siel\Acumulus\ApiClient\Result
{
    public function setMainResponseKey(string $mainResponseKey, bool $isList = false): Result
    {
        // Add messages for the parameters that were passed in, so they can be checked.
        $this->addMessage($mainResponseKey, Severity::Log, 'mainResponseKey', 0);
        $this->addMessage($isList ? 'true' : 'false', Severity::Log, 'isList', 0);
        return parent::setMainResponseKey($mainResponseKey, $isList);
    }
}
