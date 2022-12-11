<?php

namespace Siel\Acumulus\Tests\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\Acumulus as BaseAcumulus;
use Siel\Acumulus\ApiClient\AcumulusResult;

class Acumulus extends BaseAcumulus
{
    /**
     * $apiFunction = "entry/timeout" will set additional curl options in
     * {@see \Siel\Acumulus\Tests\TestWebShop\ApiClient\AcumulusRequest::getCurlOptions()}
     * that will result in a timeout.
     *
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function timeout($entryId): AcumulusResult
    {
        return $this->callApiFunction('entry/timeout', ['entryid' => $entryId], true)->setMainAcumulusResponseKey('entry');
    }

    /**
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function notExisting(): AcumulusResult
    {
        return $this->callApiFunction('invoices/not_existing', [], true)->setMainAcumulusResponseKey('not_existing');
    }

    public function noEmailOnError($entryId): AcumulusResult
    {
        return $this->callApiFunction('entry/noemailonerror', ['entryid' => $entryId], true)->setMainAcumulusResponseKey('entry');
    }

    public function noEmailOnWarning($entryId): AcumulusResult
    {
        return $this->callApiFunction('entry/noemailonwarning', ['entryid' => $entryId], true)->setMainAcumulusResponseKey('entry');
    }

    /**
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function noContract(): AcumulusResult
    {
        return $this->getPicklist('invoicetemplates', [], false);
    }

    /**
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function getPicklistDiscountProfiles(): AcumulusResult
    {
        return $this->getPicklist('discountprofiles', [], true);
    }
}
