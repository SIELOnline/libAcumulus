<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $address may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Helpers\MessageCollection;

use Siel\Acumulus\Helpers\Result;

use function assert;

/**
 * EmailInvoiceAsPdfCompletor completes an {@see EmailInvoiceAsPdf}.
 *
 * After an invoice has been collected, the shop-specific part, it needs to be
 * completed, also the emailAsPdf part. Think of things like:
 * - Adding config-based properties.
 *
 * @noinspection PhpUnused  Instantiated by type name
 */
class EmailInvoiceAsPdfCompletor extends BaseCompletor
{
    /**
     * Completes an {@see EmailInvoiceAsPdf}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param EmailInvoiceAsPdf $acumulusObject
     * @param Result $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        assert($acumulusObject instanceof EmailInvoiceAsPdf);
        assert($result instanceof Result);

        $this->getContainer()->getCompletorTask('EmailInvoiceAsPdf', 'ByConfig')->complete($acumulusObject);
    }
}
