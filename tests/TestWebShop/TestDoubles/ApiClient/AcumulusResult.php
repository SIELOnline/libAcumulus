<?php
namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\AcumulusResult as BaseResult;

class AcumulusResult extends BaseResult
{
    public function setMainResponseKey(string $mainResponseKey, bool $isList = false): BaseResult
    {
        // Add messages for the parameters that were passed in, so they can be checked.
        $this->createAndAddMessage($mainResponseKey, Severity::Log, 'mainResponseKey');
        $this->createAndAddMessage($isList ? 'true' : 'false', Severity::Log, 'isList');
        return parent::setMainResponseKey($mainResponseKey, $isList);
    }
}
