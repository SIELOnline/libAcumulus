<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * EmailAsPdfType defines for what type of pdf the EmailAsPdfTarget section will
 * be used.
 *
 * @nth: PHP8.1: enumeration.
 */
interface EmailAsPdfType
{
    public const Invoice = 'EmailInvoiceAsPdf';
    public const PackingSlip = 'EmailPackingSlipAsPdf';
}
