<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Invoice;

use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Meta;

use function assert;
use function count;
use function in_array;

/**
 * CompleteIssueDate completes the {@see \Siel\Acumulus\Data\Invoice::$issueDate}
 * property of an {@see \Siel\Acumulus\Data\Invoice}.
 */
class CompleteIssueDate extends BaseCompletorTask
{
    /**
     * Completes the {@see \Siel\Acumulus\Data\Invoice::$issueDate} property.
     *
     * @param \Siel\Acumulus\Data\Invoice $acumulusObject
     * @param int ...$args
     *   Additional parameters: none.
     *     - 0: The source for the issue date (comes from a setting): one of the
     *       Config::IssueDateSource_... constants:
     *         - Config::IssueDateSource_Transfer: Use the transfer date (today).
     *         - Config::IssueDateSource_OrderCreate: Use the order create date.
     *         - Config::IssueDateSource_InvoiceCreate: Use the shop invoice
     *           date (fallback to the order date if no invoice exists).
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        assert($acumulusObject instanceof Invoice);
        $dateToUse = $this->configGet('dateToUse');
        switch ($dateToUse) {
            case Config::IssueDateSource_InvoiceCreate:
                $date = $acumulusObject->metadataGet(Meta::ShopInvoiceDate) ?? $acumulusObject->metadataGet(Meta::Date);
                break;
            case Config::IssueDateSource_OrderCreate:
                $date = $acumulusObject->metadataGet(Meta::Date);
                break;
            case Config::IssueDateSource_Transfer:
                $date = null;
                break;
            default:
                assert(false);
        }
        if ($date !== null) {
            $acumulusObject->issueDate = $date;
        }
    }
}
