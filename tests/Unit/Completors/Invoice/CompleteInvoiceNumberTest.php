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

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
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

    public static function invoiceNumberDataProvider(): array
    {
        return [
            [Config::InvoiceNrSource_Acumulus, '2022009', 'BR2022003', null],
            [Config::InvoiceNrSource_Acumulus, 2022009, 'BR2022003', null],
            [Config::InvoiceNrSource_Acumulus, 2022009, 'BR2022003', 1234, 1234],
            [Config::InvoiceNrSource_ShopOrder, '2022009', 'BR2022003', 2022009],
            [Config::InvoiceNrSource_ShopOrder, '2022009', 'BR2022003', 1234, 1234],
            [Config::InvoiceNrSource_ShopInvoice, '2022009', 'BR2022003', 2022003],
            [Config::InvoiceNrSource_ShopInvoice, '2022009', 'BR2022003', 1234, 1234],
            [Config::InvoiceNrSource_ShopOrder, null, 'BR2022003', null],
            [Config::InvoiceNrSource_ShopInvoice, '2022009', null, 2022009],
            [Config::InvoiceNrSource_ShopInvoice, null, null, null],
            [Config::InvoiceNrSource_ShopInvoice, null, 0, null],
            [Config::InvoiceNrSource_ShopInvoice, null, 'ABCD', null],
            [Config::InvoiceNrSource_ShopInvoice, null, 'BR2023001D', 2023001],
            [Config::InvoiceNrSource_ShopInvoice, 10, 11, 11],
        ];
    }

    /**
     * @dataProvider invoiceNumberDataProvider
     */
    public function testComplete(
        int $sourceToUse,
        int|string|null $orderReference,
        int|string|null $invoiceReference,
        ?int $expected,
        ?int $filledIn = null
    ): void {
        $config = $this->getContainer()->getConfig();
        $config->set('invoiceNrSource', $sourceToUse);
        $completor = $this->getContainer()->getCompletorTask('Invoice','InvoiceNumber');
        $invoice = $this->getInvoice();
        if ($filledIn !== null) {
            $invoice->number = $filledIn;
        }
        $invoice->metadataSet(Meta::SourceReference, $orderReference);
        $invoice->metadataSet(Meta::ShopInvoiceReference, $invoiceReference);
        $completor->complete($invoice, $sourceToUse);
        $this->assertSame($expected, $invoice->number);
    }
}
