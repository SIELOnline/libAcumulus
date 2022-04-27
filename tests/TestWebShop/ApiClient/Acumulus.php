<?php

namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\Acumulus as BaseAcumulus;
use Siel\Acumulus\ApiClient\AcumulusResult;

class Acumulus extends BaseAcumulus
{
    /**
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function timeout(): AcumulusResult
    {
        return $this->callApiFunction('entry/timeout', ['entryid' => 45691627], true)->setMainAcumulusResponseKey('invoice');
    }

    /**
     * @throws \Siel\Acumulus\ApiClient\AcumulusException|\Siel\Acumulus\ApiClient\AcumulusResponseException
     */
    public function notExisting(): AcumulusResult
    {
        return $this->callApiFunction('invoices/not_existing', [], false)->setMainAcumulusResponseKey('not_existing');
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
