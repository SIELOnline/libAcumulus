<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Tests\Data\GetTestData;

/**
 * SendInvoiceTest tests the process of creation and sending process.
 */
class InvoiceCreateTest extends TestCase
{
    private static Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        self::$container = new Container('TestWebShop', 'nl');
        self::$container->addTranslations('Translations', 'Invoice');
    }

    private function getContainer(): Container
    {
        return self::$container;
    }

    private function getInvoiceSource(): Source
    {
        $objects = (new GetTestData())->getJson();
        $order = $objects->order;
        return $this->getContainer()->createSource(Source::Order, $order);
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    public function testCreate(): void
    {
        $invoiceSource = $this->getInvoiceSource();
        $invoiceAddResult = $this->getContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateAndCompleteInvoice()');
        $invoice = $this->getContainer()->getInvoiceCreate()->create($invoiceSource, $invoiceAddResult);
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
}
