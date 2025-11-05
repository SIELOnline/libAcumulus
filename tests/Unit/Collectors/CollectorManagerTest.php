<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Collectors;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Data\GetTestData;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * CollectorManagerTest test the CollectorManager and the various collectors
 * used by the manager.
 */
class CollectorManagerTest extends TestCase
{
    use AcumulusContainer;

    private function getInvoiceSource(): Source
    {
        $objects = (new GetTestData())->getJson();
        $order = $objects->order;
        return self::getContainer()->createSource(Source::Order, $order);
    }

    public function testCollectInvoice(): void
    {
        $manager = self::getContainer()->getCollectorManager();
        $invoice = $manager->collectInvoiceForSource(
            $this->getInvoiceSource(),
            self::getContainer()->createInvoiceAddResult('CollectorManagerTest::testCollectInvoice()')
        );

        self::assertNull($invoice->concept);
        self::assertNull($invoice->conceptType);
        self::assertNull($invoice->number);
        self::assertNull($invoice->vatType);
        self::assertNull($invoice->issueDate);
        self::assertNull($invoice->costCenter);
        self::assertNull($invoice->accountNumber);
        self::assertNull($invoice->template);
        self::assertSame(Api::PaymentStatus_Paid, $invoice->paymentStatus);
        self::assertEquals(new DateTimeImmutable('2022-12-02'), $invoice->paymentDate);
        self::assertSame('Bestelling 3', $invoice->description);
        self::assertNull($invoice->descriptionText);
        self::assertNull($invoice->invoiceNotes);

        $meta = $invoice->getMetadata();
        self::assertSame(Source::Order, $meta->get(Meta::SourceType));
        self::assertSame(3, $meta->get(Meta::SourceId));
        self::assertSame(3, $meta->get(Meta::SourceReference));
        self::assertEquals('2022-12-01', $meta->get(Meta::SourceDate));
        self::assertSame('pending', $meta->get(Meta::SourceStatus));
        self::assertSame('paypal', $meta->get(Meta::PaymentMethod));
        self::assertNull($meta->get(Meta::ShopInvoiceId));
        self::assertNull($meta->get(Meta::ShopInvoiceReference));
        self::assertNull($meta->get(Meta::ShopInvoiceDate));

        /** @var \Siel\Acumulus\Invoice\Currency $currency */
        $currency = $meta->get(Meta::Currency);
        self::assertSame('EUR', $currency->currency);
        self::assertEqualsWithDelta(1.0, $currency->rate, 0.000001);
        self::assertFalse($currency->doConvert);

        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $meta->get(Meta::Totals);
        self::assertEqualsWithDelta(50.4, $totals->amountInc, 0.001);
        self::assertEqualsWithDelta(8.75, $totals->amountVat, 0.001);
        self::assertEqualsWithDelta(41.65, $totals->amountEx, 0.001);

        self::assertNotNull($invoice->getCustomer());
        self::assertCount(0, $invoice->getLines());
    }

    public function testCollectCustomer(): void
    {
        $collectorManager = self::getContainer()->getCollectorManager();
        $collectorManager->getPropertySources()->clear()->add('source', $this->getInvoiceSource());
        $customer = $collectorManager->collectCustomer();

        self::assertNull($customer->contactId);
        self::assertNull($customer->type);
        self::assertNull($customer->vatTypeId);
        self::assertSame('2', $customer->contactYourId);
        self::assertNull($customer->contactStatus);
        self::assertSame('Beste Erwin', $customer->salutation);

        self::assertNull($customer->website);
        self::assertNull($customer->vatNumber);
        self::assertSame('0123456789', $customer->telephone);
        self::assertSame('+33612345978', $customer->telephone2);
        self::assertNull($customer->fax);
        self::assertSame('customer@example.com', $customer->email);
        self::assertNull($customer->overwriteIfExists);
        self::assertNull($customer->bankAccountNumber);
        self::assertNull($customer->mark);
        self::assertNull($customer->disableDuplicates);

        // Test that the child objects are there and have correct values.
        self::assertSame('Lindelaan 4', $customer->getInvoiceAddress()->address1);
        self::assertSame('Lindelaan 5', $customer->getShippingAddress()->address1);
    }

    public function testCollectAddress(): void
    {
        $collectorManager = self::getContainer()->getCollectorManager();
        $collectorManager->getPropertySources()->clear()->add('source', $this->getInvoiceSource());
        $invoiceAddress = $collectorManager->collectAddress(AddressType::Invoice);

        self::assertSame('Buro RaDer', $invoiceAddress->companyName1);
        self::assertNull($invoiceAddress->companyName2);
        self::assertSame('Erwin Derksen', $invoiceAddress->fullName);
        self::assertSame('Lindelaan 4', $invoiceAddress->address1);
        self::assertSame('Achter de Linden', $invoiceAddress->address2);
        self::assertSame('1234 AB', $invoiceAddress->postalCode);
        self::assertSame('Utrecht', $invoiceAddress->city);
        self::assertNull($invoiceAddress->country);
        self::assertEqualsIgnoringCase('NL', $invoiceAddress->countryCode);
        self::assertNull($invoiceAddress->countryAutoName);
        self::assertNull($invoiceAddress->countryAutoNameLang);

        $shippingAddress = self::getContainer()->getCollectorManager()
            ->collectAddress(AddressType::Shipping);
        self::assertSame('Buro RaDer', $shippingAddress->companyName1);
        self::assertNull($shippingAddress->companyName2);
        self::assertSame('Erwin Derksen', $shippingAddress->fullName);
        self::assertSame('Lindelaan 5', $shippingAddress->address1);
        self::assertSame('Achter den Linden', $shippingAddress->address2);
        self::assertSame('1234 AB', $shippingAddress->postalCode);
        self::assertSame('Utrecht', $shippingAddress->city);
        self::assertNull($shippingAddress->country);
        self::assertEqualsIgnoringCase('NL', $shippingAddress->countryCode);
    }

    public function testCollectEmailAsPdf(): void
    {
        $collectorManager = self::getContainer()->getCollectorManager();
        $collectorManager->getPropertySources()->clear()->add('source', $this->getInvoiceSource());
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $invoiceEmailAsPdf */
        $invoiceEmailAsPdf = $collectorManager->collectEmailAsPdf(EmailAsPdfType::Invoice);

        self::assertNull($invoiceEmailAsPdf->emailFrom);
        self::assertSame('customer@example.com', $invoiceEmailAsPdf->emailTo);
        self::assertSame('dev@example.com', $invoiceEmailAsPdf->emailBcc);
        self::assertSame('Factuur voor bestelling 3', $invoiceEmailAsPdf->subject);
        self::assertNull($invoiceEmailAsPdf->message);
        self::assertNull($invoiceEmailAsPdf->gfx);
        self::assertNull($invoiceEmailAsPdf->ubl);

        /** @var \Siel\Acumulus\Data\EmailPackingSlipAsPdf $packingSlipEmailAsPdf */
        $packingSlipEmailAsPdf = self::getContainer()->getCollectorManager()
            ->collectEmailAsPdf(EmailAsPdfType::PackingSlip);

        self::assertNull($packingSlipEmailAsPdf->emailFrom);
        self::assertSame('customer@example.com', $packingSlipEmailAsPdf->emailTo);
        self::assertSame('dev@example.com', $packingSlipEmailAsPdf->emailBcc);
        self::assertSame('Pakbon voor bestelling 3', $packingSlipEmailAsPdf->subject);
        self::assertNull($packingSlipEmailAsPdf->message);
        self::assertNull($packingSlipEmailAsPdf->gfx);
    }
}
