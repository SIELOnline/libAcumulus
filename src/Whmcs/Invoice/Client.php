<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Client as BaseClient;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Whmcs\Helpers\LocalApiTrait;
use WHMCS\User\Client as WhmcsClient;

/**
 * Client implements the WHMCS specific parts of a {@see \Siel\Acumulus\Invoice\Client} of
 * a {@see Source}.
 */
class Client extends BaseClient
{
    use LocalApiTrait;

    protected function setShopObject(): void
    {
//        $this->shopObject = $this->localApi()->getClient($this->getId());
        $this->shopObject = WhmcsClient::find($this->getId());
    }

    protected function setId(): void
    {
        throw new RuntimeException('This method is not expected to be called in WHMCS');
    }
}
