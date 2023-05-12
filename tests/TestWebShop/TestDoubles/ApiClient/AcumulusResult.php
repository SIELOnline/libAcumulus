<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\TestDoubles\ApiClient;

use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\AcumulusResult as BaseResult;

/**
 * AcumulusResult adds some features used in testing the behaviour of its parent
 * class.
 */
class AcumulusResult extends BaseResult
{
    protected function getContentFormat(): string
    {
        // This may have to be more advanced with future tests.
        return 'json';
    }

    public function setMainAcumulusResponseKey(string $mainResponseKey, bool $isList = false): BaseResult
    {
        // Add messages for the parameters that were passed in, so they can be checked.
        $this->createAndAddMessage($mainResponseKey, Severity::Log, 'mainResponseKey');
        $this->createAndAddMessage($isList ? 'true' : 'false', Severity::Log, 'isList');
        return parent::setMainAcumulusResponseKey($mainResponseKey, $isList);
    }
}
