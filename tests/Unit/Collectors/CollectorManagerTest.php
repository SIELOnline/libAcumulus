<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Collectors;

use DateTime;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Unit\GetTestData;

/**
 * CollectorManagerTest test the CollectorManager and the various collectors
 * used by the manager.
 */
class CollectorManagerTest extends TestCase
{
    private Container $container;

    private function getInvoiceSource(): Source
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $objects = (new GetTestData())->get();
        $order = $objects->order;
        return $this->getContainer()->createSource(Source::Order, $order);
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('Tests\TestWebShop', 'nl');
        $this->container->addTranslations('Translations', 'Invoice');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    private function getContainer(): Container
    {
        return $this->container;
    }

    public function testCollectInvoice(): void
    {
        $manager = $this->getContainer()->getCollectorManager();
        $invoice = $manager->collectInvoice($this->getInvoiceSource());

        $this->assertNull($invoice->concept);
        $this->assertNull($invoice->conceptType);
        $this->assertNull($invoice->number);
        $this->assertNull($invoice->vatType);
        $this->assertNull($invoice->issueDate);
        $this->assertNull($invoice->costCenter);
        $this->assertNull($invoice->accountNumber);
        $this->assertNull($invoice->template);
        $this->assertSame(Api::PaymentStatus_Paid, $invoice->paymentStatus);
        $this->assertEquals(new DateTime('2022-12-02'), $invoice->paymentDate);
        $this->assertSame('Bestelling 3', $invoice->description);
        $this->assertNull($invoice->descriptionText);
        $this->assertNull($invoice->invoiceNotes);

        $meta = $invoice->getMetadata();
        $this->assertSame(Source::Order, $meta->get(Meta::ShopSourceType));
        $this->assertSame(3, $meta->get(Meta::Id));
        $this->assertSame(3, $meta->get(Meta::Reference));
        $this->assertEquals('2022-12-01', $meta->get(Meta::ShopSourceDate));
        $this->assertSame('pending', $meta->get(Meta::Status));
        $this->assertSame('paypal', $meta->get(Meta::PaymentMethod));
        $this->assertNull($meta->get(Meta::ShopInvoiceId));
        $this->assertNull($meta->get(Meta::ShopInvoiceReference));
        $this->assertNull($meta->get(Meta::ShopInvoiceDate));

        /** @var \Siel\Acumulus\Invoice\Currency $currency */
        $currency = $meta->get(Meta::Currency);
        $this->assertSame('EUR', $currency->currency);
        $this->assertEqualsWithDelta(1.0, $currency->rate, 0.000001);
        $this->assertFalse($currency->doConvert);

        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $meta->get(Meta::Totals);
        $this->assertEqualsWithDelta(50.4, $totals->amountInc, 0.001);
        $this->assertEqualsWithDelta(8.75, $totals->amountVat, 0.001);
        $this->assertEqualsWithDelta(41.65, $totals->amountEx, 0.001);

        $this->assertNotNull($invoice->getCustomer());
        $this->assertCount(0, $invoice->getLines());
    }

    public function testCollectCustomer(): void
    {
        $manager = $this->getContainer()->getCollectorManager();
        $customer = $manager->collectCustomer($this->getInvoiceSource());

        $this->assertNull($customer->contactId);
        $this->assertNull($customer->type);
        $this->assertNull($customer->vatTypeId);
        $this->assertSame('2', $customer->contactYourId);
        $this->assertNull($customer->contactStatus);

        $this->assertNull($customer->website);
        $this->assertNull($customer->vatNumber);
        $this->assertSame('0123456789', $customer->telephone);
        $this->assertSame('0612345978', $customer->telephone2);
        $this->assertNull($customer->fax);
        $this->assertSame('customer@example.com', $customer->email);
        $this->assertNull($customer->overwriteIfExists);
        $this->assertNull($customer->bankAccountNumber);
        $this->assertNull($customer->mark);
        $this->assertNull($customer->disableDuplicates);

        // Test that the child objects are there and have correct values.
        $this->assertSame('Lindelaan 4', $customer->getInvoiceAddress()->address1);
        $this->assertSame('Lindelaan 5', $customer->getShippingAddress()->address1);
    }

    public function testCollectAddress(): void
    {
        $manager = $this->getContainer()->getCollectorManager();
        $invoiceAddress = $manager->collectAddress($this->getInvoiceSource(), AddressType::Invoice);

        $this->assertSame('Buro RaDer', $invoiceAddress->companyName1);
        $this->assertNull($invoiceAddress->companyName2);
        $this->assertSame('Erwin Derksen', $invoiceAddress->fullName);
        $this->assertSame('Beste Erwin', $invoiceAddress->salutation);
        $this->assertSame('Lindelaan 4', $invoiceAddress->address1);
        $this->assertSame('Achter de Linden', $invoiceAddress->address2);
        $this->assertSame('1234 AB', $invoiceAddress->postalCode);
        $this->assertSame('Utrecht', $invoiceAddress->city);
        $this->assertNull($invoiceAddress->country);
        $this->assertEqualsIgnoringCase('NL', $invoiceAddress->countryCode);
        $this->assertNull($invoiceAddress->countryAutoName);
        $this->assertNull($invoiceAddress->countryAutoNameLang);

        $shippingAddress = $manager->collectAddress($this->getInvoiceSource(), AddressType::Shipping);
        $this->assertSame('Buro RaDer', $shippingAddress->companyName1);
        $this->assertNull($shippingAddress->companyName2);
        $this->assertSame('Erwin Derksen', $shippingAddress->fullName);
        $this->assertSame('Beste Erwin', $shippingAddress->salutation);
        $this->assertSame('Lindelaan 5', $shippingAddress->address1);
        $this->assertSame('Achter den Linden', $shippingAddress->address2);
        $this->assertSame('1234 AB', $shippingAddress->postalCode);
        $this->assertSame('Utrecht', $shippingAddress->city);
        $this->assertNull($shippingAddress->country);
        $this->assertEqualsIgnoringCase('NL', $shippingAddress->countryCode);
    }

    public function testCollectEmailAsPdf(): void
    {
        $manager = $this->getContainer()->getCollectorManager();
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $invoiceEmailAsPdf */
        $invoiceEmailAsPdf = $manager->collectEmailAsPdf($this->getInvoiceSource(), EmailAsPdfType::Invoice);

        $this->assertNull($invoiceEmailAsPdf->emailFrom);
        $this->assertSame('customer@example.com', $invoiceEmailAsPdf->emailTo);
        $this->assertSame('dev@example.com', $invoiceEmailAsPdf->emailBcc);
        $this->assertSame('Factuur voor bestelling 3', $invoiceEmailAsPdf->subject);
        $this->assertNull($invoiceEmailAsPdf->message);
        $this->assertNull($invoiceEmailAsPdf->gfx);
        $this->assertNull($invoiceEmailAsPdf->ubl);

        /** @var \Siel\Acumulus\Data\EmailPackingSlipAsPdf $packingSlipEmailAsPdf */
        $packingSlipEmailAsPdf = $manager->collectEmailAsPdf($this->getInvoiceSource(), EmailAsPdfType::PackingSlip);

        $this->assertNull($packingSlipEmailAsPdf->emailFrom);
        $this->assertSame('customer@example.com', $packingSlipEmailAsPdf->emailTo);
        $this->assertSame('dev@example.com', $packingSlipEmailAsPdf->emailBcc);
        $this->assertSame('Pakbon voor bestelling 3', $packingSlipEmailAsPdf->subject);
        $this->assertNull($packingSlipEmailAsPdf->message);
        $this->assertNull($packingSlipEmailAsPdf->gfx);
    }
}
