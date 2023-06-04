<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Data;

use Error;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Data\Invoice;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Meta;

/**
 * InvoiceTest test the {@see \Siel\Acumulus\Data\Invoice} class.
 */
class InvoiceTest extends TestCase
{

    public function testIsZeroAmount(): void
    {
        $invoice = new Invoice();
        $this->assertFalse($invoice->isZeroAmount());

        $totals = new Totals(null, 2.1, 10.0);
        $invoice->metadataSet(Meta::Totals, $totals);
        $this->assertFalse($invoice->isZeroAmount());

        $totals = new Totals(0, 0);
        $invoice->metadataSet(Meta::Totals, $totals);
        $this->assertTrue($invoice->isZeroAmount());
    }

    public function testToArrayNoCustomer(): void
    {
        $this->expectException(Error::class);
        $invoice = new Invoice();
        $invoice->toArray();
    }

    public function testToArrayNoEmailAsPdf(): void
    {
        $this->expectException(Error::class);
        $invoice = new Invoice();
        $customer = new Customer();
        $address = new Address();
        $customer->setInvoiceAddress($address);
        $invoice->setCustomer($customer);
        $invoice->metadataSet(Meta::AddEmailAsPdfSection, true);
        $invoice->toArray();
    }

    /**
     * Tests {@see \Siel\Acumulus\Data\Invoice::toArray()}.
     *
     * This tests the construction of the multi-level array. So it also tests
     * {@see \Siel\Acumulus\Data\Customer::toArray()}.
     *
     * It does not test that all properties and metadata get correctly added, that is
     * tested by {@see \Siel\Acumulus\Tests\Unit\Data\AcumulusObjectTest::testToArray()}.
     */
    public function testToArray(): void
    {
        $invoice = new Invoice();
        $invoice->concept = Api::Concept_Yes;
        $customer = new Customer();
        $customer->email = 'test@example.com';
        $customer->setMainAddress(AddressType::Invoice);
        $address1 = new Address();
        $address1->address1 = 'Street 1';
        $customer->setInvoiceAddress($address1);
        $address2 = new Address();
        $address2->address1 = 'Street 2';
        $customer->setShippingAddress($address2);
        $invoice->setCustomer($customer);
        $invoice->metadataSet(Meta::AddEmailAsPdfSection, true);
        $emailAsPdf = new EmailInvoiceAsPdf();
        $emailAsPdf->emailTo = 'client@example.com';
        $invoice->setEmailAsPdf($emailAsPdf);
        $a = $invoice->toArray();
        $expected = [
            'customer' => [
                'email' => 'test@example.com',
                'address1' => 'Street 1',
                'altAddress1' => 'Street 2',
                'meta-main-address' => 'invoice',
                'invoice' => [
                    'concept' => 1,
                    'line' => [],
                    'emailAsPdf' => [
                        'emailTo' => 'client@example.com',
                    ],
                    'meta-add-email-as-pdf-section' => true,
                ]
            ],
        ];
        $this->assertSame($expected, $a);
    }
}
