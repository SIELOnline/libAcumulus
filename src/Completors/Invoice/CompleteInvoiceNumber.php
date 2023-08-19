<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Meta;

use function assert;
use function is_string;

/**
 * CompleteInvoiceNumber completes the {@see \Siel\Acumulus\Data\Invoice::$number}
 * property of an {@see Invoice}.
 */
class CompleteInvoiceNumber extends BaseCompletorTask
{
    /**
     * Completes the {@see \Siel\Acumulus\Data\AcumulusObject::$number} property.
     *
     * Note that Acumulus only accepts integer values, no prefix, postfix, or filling with
     * zeros.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int ...$args
     *   Additional parameters: none.
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        // Should never be empty.
        $sourceToUse = $this->configGet('invoiceNrSource');
        switch ($sourceToUse) {
            case Config::InvoiceNrSource_ShopInvoice:
                $number = $acumulusObject->metadataGet(Meta::ShopInvoiceReference)
                    ?? $acumulusObject->metadataGet(Meta::Reference);
                break;
            case Config::InvoiceNrSource_ShopOrder:
                $number = $acumulusObject->metadataGet(Meta::Reference);
                break;
            case Config::InvoiceNrSource_Acumulus:
                $number = null;
                break;
            default:
                assert(false, __METHOD__ . ": setting 'invoiceNrSource' has an unknown value $sourceToUse");
        }
        if (is_string($number)) {
            // Remove any prefix consisting of non-numerical characters.
            $number = preg_replace('/^\D+/', '', $number);
        }
        if (!empty($number)) {
            $acumulusObject->number = (int) $number;
        }
    }
}
