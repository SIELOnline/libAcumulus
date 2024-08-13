<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * DataType defines (the short class names for) the possible data types.
 *
 * @nth: PHP8.1: enumeration.
 */
interface DataType
{
    public const Invoice = 'Invoice';
    public const Customer = 'Customer';
    public const Address = 'Address';
    public const EmailAsPdf = 'EmailAsPdf';
    public const EmailInvoiceAsPdf = 'EmailInvoiceAsPdf';
    public const EmailPackingSlipAsPdf = 'EmailPackingSlipAsPdf';
    public const Line = 'Line';
}
