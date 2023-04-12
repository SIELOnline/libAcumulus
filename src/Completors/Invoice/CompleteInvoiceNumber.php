<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Meta;

use function assert;

/**
 * CompleteInvoiceNumber completes the {@see \Siel\Acumulus\Data\Invoice::$number}
 * property of an {@see Invoice}.
 */
class CompleteInvoiceNumber extends BaseCompletorTask
{
    /**
     * Completes the {@see \Siel\Acumulus\Data\AcumulusObject::$number} property.
     *
     * Note that Acumulus only accepts real numeric values, no prefix, postfix,
     * or filling with zeros.
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
                assert(false);
        }
        if ($number !== null) {
            $acumulusObject->number = preg_replace('/\D/', '', $number);
        }
    }
}
