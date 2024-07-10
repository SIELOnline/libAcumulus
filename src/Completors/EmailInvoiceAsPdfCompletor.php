<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $address may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Helpers\MessageCollection;

/**
 * EmailInvoiceAsPdfCompletor completes an {@see \Siel\Acumulus\Data\EmailInvoiceAsPdf}.
 *
 * After an invoice has been collected, the shop specific part, it needs to be
 * completed, also the emailAsPdf part. Think of things like:
 * - Adding config based properties.
 *
 * @noinspection PhpUnused  Instantiated by type name
 */
class EmailInvoiceAsPdfCompletor extends BaseCompletor
{
    private EmailInvoiceAsPdf $emailAsPdf;

    /**
     * Completes an {@see \Siel\Acumulus\Data\Address}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param \Siel\Acumulus\Data\EmailInvoiceAsPdf $acumulusObject
     * @param \Siel\Acumulus\Helpers\MessageCollection $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        $this->emailAsPdf = $acumulusObject;
        $this->getContainer()->getCompletorTask('EmailInvoiceAsPdf', 'ByConfig')->complete($this->emailAsPdf);
    }
}
