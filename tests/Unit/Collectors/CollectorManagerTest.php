<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Collectors;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Tests\Unit\GetTestData;

/**
 * CollectorManagerTest test the CollectorManager and the various collectors
 * used by the manager.
 */
class CollectorManagerTest extends TestCase
{
    protected Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('Tests\TestWebShop', 'nl');
    }

    public function testCollectInvoice(): void
    {
        $this->assertTrue(true, '@todo: write tests for invoice collector');
        // @todo
    }

    public function testCollectCustomer(): void
    {
        $manager = $this->container->getCollectorManager();
        $objects = (new GetTestData())->get();
        $manager->addPropertySource('customer', $objects->order->customer);

        $customer = $manager->collectCustomer();
        $this->assertNull($customer->contactId);
        $this->assertSame(Api::CustomerType_Debtor, $customer->type);
        $this->assertNull($customer->vatTypeId);
        $this->assertSame('2', $customer->contactYourId);
        $this->assertTrue($customer->contactStatus);

        $this->assertNull($customer->website);
        $this->assertSame('', $customer->vatNumber);
        $this->assertSame('0123456789', $customer->telephone);
        $this->assertSame('0612345978', $customer->telephone2);
        $this->assertSame('', $customer->fax);
        $this->assertSame('customer@burorader.com', $customer->email);
        $this->assertTrue($customer->overwriteIfExists);
        $this->assertNull($customer->bankAccountNumber);
        $this->assertSame('', $customer->mark);
        $this->assertTrue($customer->disableDuplicates);

        // Test that the child objects are there and have correct values.
        $this->assertSame('Lindelaan 4', $customer->getInvoiceAddress()->address1);
        $this->assertSame('Lindelaan 5', $customer->getShippingAddress()->address1);
    }

    public function testCollectAddress(): void
    {
        $manager = $this->container->getCollectorManager();
        $objects = (new GetTestData())->get();
        $manager->addPropertySource('customer', $objects->order->customer);

        $invoiceAddress = $manager->collectAddress(AddressType::Invoice);
        $this->assertSame('Buro RaDer', $invoiceAddress->companyName1);
        $this->assertSame('', $invoiceAddress->companyName2);
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

        $shippingAddress = $manager->collectAddress(AddressType::Shipping);
        $this->assertSame('Buro RaDer', $shippingAddress->companyName1);
        $this->assertSame('', $shippingAddress->companyName2);
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
        $manager = $this->container->getCollectorManager();
        $objects = (new GetTestData())->get();
        $manager->addPropertySource('customer', $objects->order->customer);
        $manager->addPropertySource('order', $objects->order);

        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $invoiceEmailAsPdf */
        $invoiceEmailAsPdf = $manager->collectEmailAsPdf(EmailAsPdfType::Invoice);
        $this->assertSame('info@burorader.com', $invoiceEmailAsPdf->emailFrom);
        $this->assertSame('', $invoiceEmailAsPdf->emailTo);
        $this->assertSame('erwin@burorader.com', $invoiceEmailAsPdf->emailBcc);
        $this->assertSame('Factuur voor bestelling 3', $invoiceEmailAsPdf->subject);
        $this->assertNull($invoiceEmailAsPdf->message);
        $this->assertNull($invoiceEmailAsPdf->gfx);
        $this->assertNull($invoiceEmailAsPdf->ubl);


        /** @var \Siel\Acumulus\Data\EmailPackingSlipAsPdf $packingSlipEmailAsPdf */
        $packingSlipEmailAsPdf = $manager->collectEmailAsPdf(EmailAsPdfType::PackingSlip);
        $this->assertSame('info@burorader.com', $packingSlipEmailAsPdf->emailFrom);
        $this->assertSame('erwin@burorader.com', $packingSlipEmailAsPdf->emailTo);
        $this->assertSame('', $packingSlipEmailAsPdf->emailBcc);
        $this->assertSame('Pakbon voor bestelling 3', $packingSlipEmailAsPdf->subject);
        $this->assertNull($packingSlipEmailAsPdf->message);
        $this->assertNull($packingSlipEmailAsPdf->gfx);
    }
}
