<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;

/**
 * CompleteMultiLineInfoTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteMultiLineProperties} class.
 */
class CompleteMultiLinePropertiesTest extends TestCase
{
    private Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
        $this->container->addTranslations('Translations', 'Invoice');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    private function getContainer(): Container
    {
        return $this->container;
    }

    private function getInvoice(): Invoice
    {
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getContainer()->createAcumulusObject(DataType::Invoice);
        return $invoice;
    }

    public function testComplete(): void
    {
        $completor = $this->getContainer()->getCompletorTask('Invoice','MultiLineProperties');
        $invoice = $this->getInvoice();

        $invoice->descriptionText = ' ';
        $completor->complete($invoice);
        $this->assertSame(' ', $invoice->descriptionText);
        $this->assertNull($invoice->invoiceNotes);

        $invoice->invoiceNotes = 'a';
        $this->assertSame('a', $invoice->invoiceNotes);
        $this->assertSame(' ', $invoice->descriptionText);

        $invoice->descriptionText = '1\r\n2\t2\r\n3\r\n';
        $invoice->invoiceNotes = "\r\n1\r\n2\t2\r\n3\r\n";
        $completor->complete($invoice);
        $this->assertSame('1\r\n2\t2\r\n3\r\n', $invoice->descriptionText);
        $this->assertSame('\n1\n2\t2\n3\n', $invoice->invoiceNotes);

        $invoice->descriptionText = "1\r\n2\r3\n";
        $invoice->invoiceNotes = "\r\n\n\r1\n\r2\t\t2\t2\r\n\r\n3";
        $completor->complete($invoice);
        $this->assertSame('1\n2\n3\n', $invoice->descriptionText);
        $this->assertSame('\n\n\n1\n\n2\t\t2\t2\n\n3', $invoice->invoiceNotes);
    }
}
