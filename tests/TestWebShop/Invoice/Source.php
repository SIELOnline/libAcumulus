<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Invoice;

use DateTime;
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
    protected function setSource(): void
    {
        $this->shopSource = new stdClass();
        $this->getSource()->type = $this->getType();
        $this->getSource()->id = $this->id;
    }

    protected function setId(): void
    {
        $this->id = (int) $this->getSource()->id;
    }

    public function getDate(): string
    {
        return $this->getSource()->date;
    }

    public function getStatus(): string
    {
        return 'pending';
    }

    public function getPaymentMethod(): ?string
    {
        return $this->getSource()->payment->provider;
    }

    public function getPaymentStatus(): int
    {
        return $this->getSource()->paid ? Api::PaymentStatus_Paid : Api::PaymentStatus_Due;
    }

    public function getPaymentDate(): ?string
    {
        try {
            $timestamp = $this->getSource()->payment->timestamp;
            $date = new DateTime($timestamp);
            $result = $date->format('Y-m-d');
        } catch (Exception $e) {
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
        return new Totals($this->getSource()->amount, $this->getSource()->amount_vat);
    }

    protected function getShopOrderOrId(): int
    {
        return $this->getSource()->id;
    }

    protected function getShopCreditNotesOrIds(): array
    {
        return [];
    }
}
