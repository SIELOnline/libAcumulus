<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Collectors;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\EmailAsPdfTarget;
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
        // @todo
    }

    public function testCollectCustomer(): void
    {
        // @todo
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

        $invoiceEmailAsPdf = $manager->collectEmailAsPdf(EmailAsPdfTarget::Invoice);

        $packingSlipEmailAsPdf = $manager->collectEmailAsPdf(EmailAsPdfTarget::PackingSlip);
        // @todo
    }
}
