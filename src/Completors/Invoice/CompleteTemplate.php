<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Data\AcumulusObject;

use Siel\Acumulus\Data\Invoice;

use Siel\Acumulus\Data\PropertySet;

use function assert;

/**
 * CompleteTemplate completes the {@see \Siel\Acumulus\Data\Invoice::$template}
 * property of an {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteTemplate extends BaseCompletorTask
{
    /**
     * Completes the {@see \Siel\Acumulus\Data\Invoice::$template} property.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int ...$args
     *   Additional parameters: none.
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        // Will be 0 (not null) when config is missing.
        $defaultInvoiceTemplate = $this->configGet('defaultInvoiceTemplate');
        // 0 is a valid value.
        $defaultInvoicePaidTemplate = $this->configGet('defaultInvoicePaidTemplate');
        if ($acumulusObject->paymentStatus === Api::PaymentStatus_Due || $defaultInvoicePaidTemplate === 0) {
            $template = $defaultInvoiceTemplate;
        } else {
            $template = $defaultInvoicePaidTemplate;
        }
        $acumulusObject->setTemplate($template, PropertySet::NotOverwriteNotEmpty);
    }
}
