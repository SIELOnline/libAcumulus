<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Source;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Tests\AcumulusTestUtils;
use Siel\Acumulus\Tests\Data\GetTestData;
use Siel\Acumulus\TestWebShop\Helpers\Event;

/**
 * SendInvoiceTest tests the process of creation and sending process.
 */
class InvoiceCreateTest extends TestCase
{
    use AcumulusTestUtils;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'get';
    }

    private function getInvoiceSource(): Source
    {
        $objects = (new GetTestData())->getJson();
        $order = $objects->order;
        return self::getContainer()->createSource(Source::Order, $order);
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    public function testCreate(): void
    {
        $invoiceSource = $this->getInvoiceSource();
        $invoiceAddResult = self::getContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoice = self::getContainer()->getInvoiceCreate()->create($invoiceSource, $invoiceAddResult);
        $result = $invoice->toArray();

        // Do some basic tests: at all levels, we just check some key(s) being available.
        $this->assertCount(1, $result);
        $this->assertArrayHasKey(Fld::Customer, $result);
        $customer = $result[Fld::Customer];
        $this->assertArrayHasKey(Fld::Email, $customer);
        $this->assertArrayHasKey(Fld::FullName, $customer);
        $this->assertArrayHasKey(Fld::AltFullName, $customer);
        $this->assertArrayHasKey(Fld::Invoice, $customer);
        $invoice = $customer[Fld::Invoice];
        $this->assertArrayHasKey(Fld::Concept, $invoice);
        $this->assertIsArray($invoice[Fld::Line]);
    }

    /**
     * Tests the {@see Fld::WarehouseCountry} field in an {@see \Siel\Acumulus\Data\Invoice}.
     *
     * Note that this tests works because BE and NL have the same high VAT rate.
     */
    public function testCreateWithWarehouseCountry(): void
    {
        $invoiceSource = $this->getInvoiceSource();
        $invoiceAddResult = self::getContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoice = self::getContainer()->getInvoiceCreate()->create($invoiceSource, $invoiceAddResult);
        // Verify that the normal vat type has been set
        self::assertSame(Api::VatType_National, $invoice->vatType);

        Event::registerHook(Event::INVOICE_COLLECT_AFTER, static function (Invoice $invoice) {
            $invoice->setWarehouseCountry('BE');
        });
        $invoiceAddResult = self::getContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoice = self::getContainer()->getInvoiceCreate()->create($invoiceSource, $invoiceAddResult);
        // Verify that the warehouse country has been taken into account.
        self::assertSame(Api::VatType_EuVat, $invoice->vatType);
    }
}
