<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\TestWebShop\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Meta;
use stdClass;

/**
 * Wraps a TestWebShop order or refund in an invoice source object.
 */
class Source extends BaseSource
{
    protected function setShopSource(): void
    {
        $this->shopSource = new stdClass();
        $this->getShopSource()->type = $this->getType();
        $this->getShopSource()->id = $this->id;
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopSource()->id;
    }

    public function getReference()
    {
        return $this->getShopSource()->id;
    }

    public function getDate(): string
    {
        return '2023-02-01';
    }

    public function getStatus()
    {
        return 'pending';
    }

    public function getPaymentMethod()
    {
        return 3;
    }

    public function getPaymentStatus(): int
    {
        return Api::PaymentStatus_Due;
    }

    public function getPaymentDate(): ?string
    {
        return '2023-02-03';
    }

    public function getCountryCode(): string
    {
        return 'nl';
    }

    /**
     * {@inheritdoc}
     *
     * MyWebShop stores the internal currency id, so look up the currency
     * object first then extract the ISO code for it.
     */
    public function getCurrency(): array
    {
        return [
            Meta::Currency => 'EUR',
            Meta::CurrencyRate => 1.0,
            Meta::CurrencyDoConvert => false,
        ];
    }

    protected function getAvailableTotals(): array
    {
        return [
            'meta-invoice-amount' => 10.0,
            'meta-invoice-amountinc' => 12.10,
            'meta-invoice-vatamount' => 2.10,
        ];
    }

    protected function getShopOrderOrId()
    {
        return 3;
    }

    protected function getShopCreditNotesOrIds(): array
    {
        return [];
    }
}
