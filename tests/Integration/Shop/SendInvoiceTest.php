<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use Siel\Acumulus\Completors\InvoiceCompletor;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Shop\SendInvoice;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Tests\Unit\GetTestData;

/**
 * SendInvoiceTest tests the process of creation and sending process.
 */
class SendInvoiceTest extends TestCase
{
    private static Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        self::$container = new Container('TestWebShop', 'nl');
        self::$container->addTranslations('Translations', 'Invoice');
    }

    /**
     * Returns the (completed) test invoice as an array.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     */
    private function getInvoiceArray(): array
    {
        return json_decode(
            '{
                "customer": {
                    "type": "1",
                    "vatTypeId": "1",
                    "contactYourId": "2",
                    "contactStatus": "1",
                    "telephone": "0123456789",
                    "telephone2": "0612345978",
                    "email": "customer@example.com",
                    "overwriteIfExists": "1",
                    "companyName1": "Buro RaDer",
                    "fullName": "Erwin Derksen",
                    "salutation": "Beste Erwin",
                    "address1": "Lindelaan 4",
                    "address2": "Achter de Linden",
                    "postalCode": "1234 AB",
                    "city": "Utrecht",
                    "countryCode": "NL",
                    "meta-address-type": "invoiceAddress",
                    "altCompanyName1": "Buro RaDer",
                    "altFullName": "Erwin Derksen",
                    "altSalutation": "Beste Erwin",
                    "altAddress1": "Lindelaan 5",
                    "altAddress2": "Achter den Linden",
                    "altPostalCode": "1234 AB",
                    "altCity": "Utrecht",
                    "altCountryCode": "NL",
                    "altMeta-address-type": "shippingAddress",
                    "invoice": {
                        "concept": "1",
                        "vatType": "1",
                        "issueDate": "2022-12-01",
                        "paymentStatus": "2",
                        "paymentDate": "2022-12-02",
                        "description": "Bestelling 3",
                        "line": [],
                        "emailAsPdf": {
                            "emailTo": "customer@example.com",
                            "emailBcc": "dev@example.com",
                            "subject": "Factuur voor bestelling 3"
                        },
                        "meta-shop-source-type": "Order",
                        "meta-id": "3",
                        "meta-reference": "3",
                        "meta-shop-source-date": "2022-12-01",
                        "meta-status": "pending",
                        "meta-payment-method": "paypal",
                        "meta-shop-invoice-id": "null",
                        "meta-shop-invoice-reference": "null",
                        "meta-shop-invoice-date": "null",
                        "meta-currency": "{\"currency\":\"EUR\",\"rate\":1.0,\"doConvert\":false}",
                        "meta-totals": "{\"amountEx\":41.65,\"amountInc\":50.4,\"amountVat\":8.75,\"vatBreakdown\":null,\"calculated\":\"amountEx\"}",
                        "meta-add-email-as-pdf-section": "true",
                        "meta-lines-amount": "0.0",
                        "meta-lines-amountinc": "0.0",
                        "meta-lines-vatamount": "0.0",
                        "meta-warning": "[\"810: Het factuurbedrag klopt niet met het totaal van de regels. Het bedrag (ex. btw) wijkt \u20ac41.65 af, het bedrag (incl. btw) wijkt \u20ac50.40 af, het btw-bedrag wijkt \u20ac8.75 af. De factuur is als concept opgeslagen. In Acumulus zijn deze onder \\\"Overzichten \u00bb Concept-facturen \/ offertes\\\" terug te vinden. Controleer en corrigeer daar de factuur. Vanwege deze waarschuwing is er ook geen PDF factuur naar de klant verstuurd. U dient dit handmatig alsnog te doen.\"]",
                        "meta-vattype-source": "Completor::checkForKnownVatType: only 1 possible vat type",
                        "meta-vattypes-possible-invoice": "[1]",
                        "meta-vattypes-possible-lines-intersection": "[]",
                        "meta-vattypes-possible-lines-union": "[]"
                    }
                }
            }',
            true
        );
    }

    private function getContainer(): Container
    {
        return self::$container;
    }

    private function getInvoiceSource(): Source
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $objects = (new GetTestData())->get();
        $order = $objects->order;
        return $this->getContainer()->createSource(Source::Order, $order);
    }

    protected function getInvoiceCompletor(): InvoiceCompletor
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getContainer()->getCompletor(DataType::Invoice);
    }

    /**
     * Tests the Creation process, i.e. collecting and completing an
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    public function testCreateInvoice(): void
    {
        $manager = $this->getContainer()->getCollectorManager();
        $invoiceSource = $this->getInvoiceSource();
        $invoice = $manager->collectInvoice($invoiceSource);
        /** @var InvoiceCompletor $invoiceCompletor */
        $invoiceCompletor = $this->getContainer()->getCompletor(DataType::Invoice);
        $result = $this->getContainer()->createInvoiceAddResult('SendInvoiceTest::testCreateInvoice()');
        $invoiceCompletor->setSource($invoiceSource)->complete($invoice, $result);

//        $expected = $this->getInvoiceArray();
        $result = $invoice->toArray();
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
