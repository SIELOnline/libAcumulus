<?php
namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\Result as BaseResult;

class Result extends BaseResult
{
    public function setMainResponseKey(string $mainResponseKey, bool $isList = false): BaseResult
    {
        // Add messages for the parameters that were passed in, so they can be checked.
        $this->addMessage($mainResponseKey, Severity::Log, 'mainResponseKey', 0);
        $this->addMessage($isList ? 'true' : 'false', Severity::Log, 'isList', 0);
        return parent::setMainResponseKey($mainResponseKey, $isList);
    }
}
