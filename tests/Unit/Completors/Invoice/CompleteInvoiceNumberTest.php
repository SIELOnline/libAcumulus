<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteInvoiceNumberTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteInvoiceNumber} class.
 */
class CompleteInvoiceNumberTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

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
        $config = self::getContainer()->getConfig();
        $config->set('invoiceNrSource', $sourceToUse);
        $completor = self::getContainer()->getCompletorTask('Invoice','InvoiceNumber');
        $invoice = $this->getInvoice();
        if ($filledIn !== null) {
            $invoice->number = $filledIn;
        }
        $invoice->metadataSet(Meta::SourceReference, $orderReference);
        $invoice->metadataSet(Meta::ShopInvoiceReference, $invoiceReference);
        $completor->complete($invoice, $sourceToUse);
        self::assertSame($expected, $invoice->number);
    }
}
