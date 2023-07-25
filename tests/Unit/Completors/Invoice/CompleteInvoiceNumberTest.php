<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * CompleteInvoiceNumberTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteInvoiceNumber} class.
 */
class CompleteInvoiceNumberTest extends TestCase
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

    public function invoiceNumberDataProvider(): array
    {
        return [
            [Config::InvoiceNrSource_Acumulus, '2022009', 'BR2022003', null],
            [Config::InvoiceNrSource_Acumulus, 2022009, 'BR2022003', null],
            [Config::InvoiceNrSource_ShopOrder, '2022009', 'BR2022003', 2022009],
            [Config::InvoiceNrSource_ShopInvoice, '2022009', 'BR2022003', 2022003],
            [Config::InvoiceNrSource_ShopOrder, null, 'BR2022003', null],
            [Config::InvoiceNrSource_ShopInvoice, '2022009', null, 2022009],
            [Config::InvoiceNrSource_ShopInvoice, null, null, null],
            [Config::InvoiceNrSource_ShopInvoice, null, 0, 0],
            [Config::InvoiceNrSource_ShopInvoice, null, 'ABCD', null],
            [Config::InvoiceNrSource_ShopInvoice, 10, 11, 11],
        ];
    }

    /**
     * @dataProvider invoiceNumberDataProvider
     *
     * @param int $sourceToUse
     * @param int|string|null $orderReference
     * @param int|string|null $invoiceReference
     * @param int|null $expected
     */
    public function testComplete(int $sourceToUse, $orderReference, $invoiceReference, ?int $expected): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set('invoiceNrSource', $sourceToUse);
        $completor = $this->getContainer()->getCompletorTask('Invoice','InvoiceNumber');
        $invoice = $this->getInvoice();
        $invoice->metadataAdd(Meta::Reference, $orderReference);
        $invoice->metadataAdd(Meta::ShopInvoiceReference, $invoiceReference);
        $completor->complete($invoice, $sourceToUse);
        $this->assertSame($expected, $invoice->number);
    }
}
