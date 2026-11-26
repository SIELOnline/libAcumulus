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
        $order = json_decode(json_encode($objects['order']), FALSE);
        return self::getContainer()->createSource(Source::Order, $order);
    }

    /** @noinspection PhpUndefinedMethodInspection */
    public function testCollectInvoice(): void
    {
        $manager = self::getContainer()->getCollectorManager();
        $invoice = $manager->collectInvoiceForSource(
            $this->getInvoiceSource(),
            self::getContainer()->createInvoiceSendResult('CollectorManagerTest::testCollectInvoice()')
        );

        static::assertNull($invoice->concept);
        static::assertNull($invoice->conceptType);
        static::assertNull($invoice->number);
        static::assertNull($invoice->vatType);
        static::assertNull($invoice->issueDate);
        static::assertNull($invoice->costCenter);
        static::assertNull($invoice->accountNumber);
        static::assertNull($invoice->template);
        static::assertSame(Api::PaymentStatus_Paid, $invoice->paymentStatus);
        static::assertSame((new DateTimeImmutable('2022-12-02'))->format(Api::DateFormat_Iso), $invoice->paymentDate->format(Api::DateFormat_Iso));
        static::assertSame('Bestelling 3', $invoice->description);
        static::assertNull($invoice->descriptionText);
        static::assertNull($invoice->invoiceNotes);

        $metadataCollection = (fn () => $this->getMetadataCollection())->call($invoice);
        static::assertSame(Source::Order, $metadataCollection->get(Meta::SourceType));
        static::assertSame(3, $metadataCollection->get(Meta::SourceId));
        static::assertSame(3, $metadataCollection->get(Meta::SourceReference));
        static::assertEquals(new DateTimeImmutable('2022-12-01'), $metadataCollection->get(Meta::SourceDate));
        static::assertSame('pending', $metadataCollection->get(Meta::SourceStatus));
        static::assertSame('paypal', $metadataCollection->get(Meta::PaymentMethod));
        static::assertNull($metadataCollection->get(Meta::ShopInvoiceId));
        static::assertNull($metadataCollection->get(Meta::ShopInvoiceReference));
        static::assertNull($metadataCollection->get(Meta::ShopInvoiceDate));

        /** @var \Siel\Acumulus\Invoice\Currency $currency */
        $currency = $metadataCollection->get(Meta::Currency);
        static::assertSame('EUR', $currency->currency);
        static::assertEqualsWithDelta(1.0, $currency->rate, 0.000001);
        static::assertFalse($currency->doConvert);

        /** @var \Siel\Acumulus\Invoice\Totals $totals */
        $totals = $metadataCollection->get(Meta::Totals);
        static::assertEqualsWithDelta(50.4, $totals->amountInc, 0.001);
        static::assertEqualsWithDelta(8.75, $totals->amountVat, 0.001);
        static::assertEqualsWithDelta(41.65, $totals->amountEx, 0.001);

        static::assertNotNull($invoice->getCustomer());
        static::assertCount(0, $invoice->getLines());
    }

    public function testCollectCustomer(): void
    {
        $collectorManager = self::getContainer()->getCollectorManager();
        $collectorManager->getPropertySources()->exchangeArray(['source' => $this->getInvoiceSource()]);
        $customer = $collectorManager->collectCustomer();

        static::assertNull($customer->contactId);
        static::assertNull($customer->type);
        static::assertNull($customer->vatTypeId);
        static::assertSame('2', $customer->contactYourId);
        static::assertNull($customer->contactStatus);
        static::assertSame('Beste Erwin', $customer->salutation);

        static::assertNull($customer->website);
        static::assertNull($customer->vatNumber);
        static::assertSame('0123456789', $customer->telephone);
        static::assertSame('+33612345978', $customer->telephone2);
        static::assertNull($customer->fax);
        static::assertSame('customer@example.com', $customer->email);
        static::assertNull($customer->overwriteIfExists);
        static::assertNull($customer->bankAccountNumber);
        static::assertNull($customer->mark);
        static::assertNull($customer->disableDuplicates);

        // Test that the child objects are there and have correct values.
        static::assertSame('Lindelaan 4', $customer->getInvoiceAddress()->address1);
        static::assertSame('Lindelaan 5', $customer->getShippingAddress()->address1);
    }

    public function testCollectAddress(): void
    {
        $collectorManager = self::getContainer()->getCollectorManager();
        $collectorManager->getPropertySources()->exchangeArray(['source' => $this->getInvoiceSource()]);
        $invoiceAddress = $collectorManager->collectAddress(AddressType::Invoice);

        static::assertSame('Buro RaDer', $invoiceAddress->companyName1);
        static::assertNull($invoiceAddress->companyName2);
        static::assertSame('Erwin Derksen', $invoiceAddress->fullName);
        static::assertSame('Lindelaan 4', $invoiceAddress->address1);
        static::assertSame('Achter de Linden', $invoiceAddress->address2);
        static::assertSame('1234 AB', $invoiceAddress->postalCode);
        static::assertSame('Utrecht', $invoiceAddress->city);
        static::assertNull($invoiceAddress->country);
        static::assertEqualsIgnoringCase('NL', $invoiceAddress->countryCode);
        static::assertNull($invoiceAddress->countryAutoName);
        static::assertNull($invoiceAddress->countryAutoNameLang);

        $shippingAddress = self::getContainer()->getCollectorManager()
            ->collectAddress(AddressType::Shipping);
        static::assertSame('Buro RaDer', $shippingAddress->companyName1);
        static::assertNull($shippingAddress->companyName2);
        static::assertSame('Erwin Derksen', $shippingAddress->fullName);
        static::assertSame('Lindelaan 5', $shippingAddress->address1);
        static::assertSame('Achter den Linden', $shippingAddress->address2);
        static::assertSame('1234 AB', $shippingAddress->postalCode);
        static::assertSame('Utrecht', $shippingAddress->city);
        static::assertNull($shippingAddress->country);
        static::assertEqualsIgnoringCase('NL', $shippingAddress->countryCode);
    }

    public function testCollectEmailAsPdf(): void
    {
        $collectorManager = self::getContainer()->getCollectorManager();
        $collectorManager->getPropertySources()->exchangeArray(['source' => $this->getInvoiceSource()]);
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $invoiceEmailAsPdf */
        $invoiceEmailAsPdf = $collectorManager->collectEmailAsPdf(EmailAsPdfType::Invoice);

        static::assertNull($invoiceEmailAsPdf->emailFrom);
        static::assertSame('customer@example.com', $invoiceEmailAsPdf->emailTo);
        static::assertSame('dev@example.com', $invoiceEmailAsPdf->emailBcc);
        static::assertSame('Factuur voor bestelling 3', $invoiceEmailAsPdf->subject);
        static::assertNull($invoiceEmailAsPdf->message);
        static::assertNull($invoiceEmailAsPdf->gfx);
        static::assertNull($invoiceEmailAsPdf->ubl);

        /** @var \Siel\Acumulus\Data\EmailPackingSlipAsPdf $packingSlipEmailAsPdf */
        $packingSlipEmailAsPdf = self::getContainer()->getCollectorManager()
            ->collectEmailAsPdf(EmailAsPdfType::PackingSlip);

        static::assertNull($packingSlipEmailAsPdf->emailFrom);
        static::assertSame('customer@example.com', $packingSlipEmailAsPdf->emailTo);
        static::assertSame('dev@example.com', $packingSlipEmailAsPdf->emailBcc);
        static::assertSame('Pakbon voor bestelling 3', $packingSlipEmailAsPdf->subject);
        static::assertNull($packingSlipEmailAsPdf->message);
        static::assertNull($packingSlipEmailAsPdf->gfx);
    }
}
