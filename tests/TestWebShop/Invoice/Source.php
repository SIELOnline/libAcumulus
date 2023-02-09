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
    /**
     * {@inheritdoc}
     */
    protected function setSource(): void
    {
        $this->source = new stdClass();
        $this->source->type = $this->getType();
        $this->source->id = $this->id;
    }

    /**
     * {@inheritdoc}
     */
    protected function setId(): void
    {
        $this->id = (int) $this->source->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getReference()
    {
        return $this->source->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getDate(): string
    {
        return '2023-02-01';
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus()
    {
        return 'pending';
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentMethod()
    {
        return 3;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus(): int
    {
        return Api::PaymentStatus_Due;
    }

    /**
     * {@inheritdoc}
     */
    public function getPaymentDate(): ?string
    {
        return '2023-02-03';
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    protected function getAvailableTotals(): array
    {
        return [
            'meta-invoice-amount' => 10.0,
            'meta-invoice-amountinc' => 12.10,
            'meta-invoice-vatamount' => 2.10,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopOrderOrId()
    {
        return 3;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopCreditNotesOrIds(): array
    {
        return [];
    }
}
