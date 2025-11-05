<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteMultiTextLineInfoTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteMultiTextLineProperties} class.
 */
class CompleteMultiTextLinePropertiesTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public function testComplete(): void
    {
        $completor = self::getContainer()->getCompletorTask('Invoice','MultiTextLineProperties');
        $invoice = $this->getInvoice();

        $invoice->descriptionText = ' ';
        $completor->complete($invoice);
        self::assertSame(' ', $invoice->descriptionText);
        self::assertNull($invoice->invoiceNotes);

        $invoice->invoiceNotes = 'a';
        self::assertSame('a', $invoice->invoiceNotes);
        self::assertSame(' ', $invoice->descriptionText);

        $invoice->descriptionText = '1\r\n2\t2\r\n3\r\n';
        $invoice->invoiceNotes = "\r\n1\r\n2\t2\r\n3\r\n";
        $completor->complete($invoice);
        self::assertSame('1\r\n2\t2\r\n3\r\n', $invoice->descriptionText);
        self::assertSame('\n1\n2\t2\n3\n', $invoice->invoiceNotes);

        $invoice->descriptionText = "1\r\n2\r3\n";
        $invoice->invoiceNotes = "\r\n\n\r1\n\r2\t\t2\t2\r\n\r\n3";
        $completor->complete($invoice);
        self::assertSame('1\n2\n3\n', $invoice->descriptionText);
        self::assertSame('\n\n\n1\n\n2\t\t2\t2\n\n3', $invoice->invoiceNotes);
    }
}
