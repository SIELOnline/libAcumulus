<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Invoice;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\Invoice\Totals;
use stdClass;

/**
 * Wraps a TestWebShop order or refund in an invoice source object.
 */
class Source extends BaseSource
{
    protected function setShopObject(): void
    {
        $this->shopObject = new stdClass();
        $this->getShopObject()->type = $this->getType();
        $this->getShopObject()->id = $this->id;
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getShopObject()->id;
    }

    public function getDate(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->getShopObject()->date);
    }

    public function getStatus(): int|string|null
    {
        return 'pending';
    }

    public function getPaymentMethod(): int|string|null
    {
        return $this->getShopObject()->payment->provider;
    }

    public function getPaymentStatus(): int
    {
        return $this->getShopObject()->paid ? Api::PaymentStatus_Paid : Api::PaymentStatus_Due;
    }

    public function getPaymentDate(): ?DateTimeInterface
    {
        try {
            $timestamp = $this->getShopObject()->payment->timestamp;
            $result = new DateTimeImmutable($timestamp);
        } catch (Exception) {
            $result = null;
        }
        return $result;
    }

    public function getCountryCode(): string
    {
        return 'nl';
    }

    public function getTotals(): Totals
    {
        return new Totals($this->getShopObject()->amount, $this->getShopObject()->amount_vat);
    }

    protected function getShopOrderOrId(): int
    {
        return $this->getShopObject()->id;
    }

    protected function createItems(): array
    {
        return [];
    }
}
