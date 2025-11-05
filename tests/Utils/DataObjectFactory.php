<?php
/**
 * @noinspection PhpIncompatibleReturnTypeInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;

/**
 * DataObjectFactory contains data object creation related test functionalities
 * for library and shop tests.
 */
trait DataObjectFactory
{
    use AcumulusContainer;

    protected function getAddress(): Address
    {
        return self::getContainer()->createAcumulusObject(DataType::Address);
    }

    protected function getCustomer(): Customer
    {
        return self::getContainer()->createAcumulusObject(DataType::Customer);
    }

    protected function getEmailInvoiceAsPdf(): EmailInvoiceAsPdf
    {
        return self::getContainer()->createAcumulusObject(DataType::EmailInvoiceAsPdf);
    }

    protected function getInvoice(): Invoice
    {
        return self::getContainer()->createAcumulusObject(DataType::Invoice);
    }

    protected function getLine(string $lineType): Line
    {
        /** @var \Siel\Acumulus\Data\Line $line */
        $line = self::getContainer()->createAcumulusObject(DataType::Line);
        $line->metadataSet(Meta::SubType, $lineType);
        return $line;
    }
}
