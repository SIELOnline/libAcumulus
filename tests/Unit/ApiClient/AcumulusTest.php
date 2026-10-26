<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Data\EmailPackingSlipAsPdf;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * Test the {@see Acumulus} class without connecting to the API itself.
 */
class AcumulusTest extends TestCase
{
    use AcumulusContainer;

    // Use the TestWebShop test doubles.
    protected static string $shopNamespace = 'TestWebShop\TestDoubles';
    protected static string $language = 'nl';

    /**
     * Each data array consists of:
     * 0: method (string): the method to call.
     * 1: args (array): the args to pass to the (public) method.
     * 2: apiFunction (string): the api function to call.
     * 3: needContract (bool): whether authorisation is required.
     * 4: message (array): submit as expected by Acumulus, created from args.
     *
     * @noinspection SpellCheckingInspection
     */
    public static function argumentsPassed(): array
    {
        // See for the meaning of each entry the parameter list of testApiCalls.
        $emailInvoiceAsPdf = new EmailInvoiceAsPdf();
        $emailInvoiceAsPdf->setEmailTo('test@example.com');
        $emailPackingslipAsPdf = new EmailPackingSlipAsPdf();
        $emailPackingslipAsPdf->setEmailTo('test@example.com');
        $invoice = new Invoice();
        $customer = new Customer();
        $customer->setInvoiceAddress(new Address());
        $invoice->setCustomer($customer);

        $stockTransaction = new StockTransaction();
        $stockTransaction->productId = 12345;
        $stockTransaction->stockAmount = 1;
        $stockTransaction->stockDescription = 'description';
        $stockTransaction->stockDate = new DateTimeImmutable('2022-02-02');

        return [
            'About' => ['getAbout', [], 'general/general_about', [], true],
            'MyAcumulus' => ['getMyAcumulus', [], 'general/my_acumulus', [], true],
            'Accounts' => ['getPicklistAccounts', [], 'picklists/picklist_accounts', ['accountstatus' => 1], true],
            'CompanyTypes' => ['getPicklistCompanyTypes', [], 'picklists/picklist_companytypes', [], false],
            'ContactTypes' => ['getPicklistContactTypes', [], 'picklists/picklist_contacttypes', [], true],
            'CostCenters' => ['getPicklistCostCenters', [], 'picklists/picklist_costcenters', [], true],
            'InvoiceTemplates' => ['getPicklistInvoiceTemplates', [], 'picklists/picklist_invoicetemplates', [], true],
            'Products' => ['getPicklistProducts', [], 'picklists/picklist_products', [], true],
            'ProductsFilter' => ['getPicklistProducts', ['test'], 'picklists/picklist_products', ['filter' => 'test'], true],
            'ProductsTag' => ['getPicklistProducts', [null, null, null, 0], 'picklists/picklist_products', ['producttagid' => 0], true],
            'ProductsSegment' => ['getPicklistProducts', [null, 40, 10], 'picklists/picklist_products', ['offset' => 40, 'rowcount' => 10], true],
            'VatInfo' => ['getVatInfo', ['nl'], 'lookups/lookup_vatinfo', ['vatcountry' => 'nl', 'vatdate' => date(Api::DateFormat_Iso)], true],
            'EuThreshold' => ['reportThresholdEuCommerce', [], 'reports/report_threshold_eu_ecommerce', [], true],
            'EuThresholdYear' => ['reportThresholdEuCommerce', [2021], 'reports/report_threshold_eu_ecommerce', ['year' => 2021], true],
            'InvoiceAdd' => ['invoiceAdd', [$invoice], 'invoices/invoice_add', ['customer' => ['invoice' => ['line' => []]]], true],
            'ConceptInfo' => ['getConceptInfo', [12345], 'invoices/invoice_concept_info', ['conceptid' => 12345], true],
            'Entry' => ['getEntry', [12345], 'entry/entry_info', ['entryid' => 12345], true],
            'SetDeleteStatus1' => [
                'setDeleteStatus',
                [12345, Api::Entry_Delete],
                'entry/entry_deletestatus_set',
                ['entryid' => 12345, 'entrydeletestatus' => 1],
                true,
            ],
            'SetDeleteStatus0' => [
                'setDeleteStatus',
                [12345, Api::Entry_UnDelete],
                'entry/entry_deletestatus_set',
                ['entryid' => 12345, 'entrydeletestatus' => 0],
                true,
            ],
            'GetPaymentStatus' => ['getPaymentStatus', ['TOKEN'], 'invoices/invoice_paymentstatus_get', ['token' => 'TOKEN'], true],
            'SetPaymentStatus' => [
                'setPaymentStatus',
                ['TOKEN', Api::PaymentStatus_Paid, '2020-02-02'],
                'invoices/invoice_paymentstatus_set',
                ['token' => 'TOKEN', 'paymentstatus' => Api::PaymentStatus_Paid, 'paymentdate' => '2020-02-02'],
                true,
            ],
            'emailInvoice' => [
                'emailInvoiceAsPdf',
                ['TOKEN', $emailInvoiceAsPdf],
                'invoices/invoice_mail',
                ['token' => 'TOKEN', 'emailaspdf' => ['emailto' => 'test@example.com']],
                true,
                ['gfx' => null,]
            ],
            'emailInvoiceReminder' => [
                'emailInvoiceAsPdf',
                ['TOKEN', $emailInvoiceAsPdf, Api::Email_Reminder],
                'invoices/invoice_mail',
                ['token' => 'TOKEN', 'invoicetype' => Api::Email_Reminder, 'emailaspdf' => ['emailto' => 'test@example.com']],
                true,
                ['gfx' => null,]
            ],
            'emailInvoiceNotes' => [
                'emailInvoiceAsPdf',
                ['TOKEN', $emailInvoiceAsPdf, 'invoiceNotes' => 'my notes'],
                'invoices/invoice_mail',
                ['token' => 'TOKEN', 'emailaspdf' => ['emailto' => 'test@example.com'], 'invoicenotes' => 'my notes'],
                true,
                ['gfx' => null,]
            ],
            'emailNoGraphics' => [
                'emailInvoiceAsPdf',
                ['TOKEN', $emailInvoiceAsPdf, 'applyGraphics' => false],
                'invoices/invoice_mail',
                ['token' => 'TOKEN', 'emailaspdf' => ['emailto' => 'test@example.com']],
                true,
                ['gfx' => 0],
            ],
            'emailPackingSlip' => [
                'emailPackingSlipAsPdf',
                ['TOKEN', $emailPackingslipAsPdf],
                'delivery/packing_slip_mail_pdf',
                ['token' => 'TOKEN', 'emailaspdf' => ['emailto' => 'test@example.com']],
                true,
                ['gfx' => null,]
            ],
            'Signup' => ['signup', [['companyname' => 'BR']], 'signup/signup', ['signup' => ['companyname' => 'BR']], false],
            'stockTransaction' => [
                'stockTransaction',
                [$stockTransaction],
                'stock/stock_add',
                ['stock' => ['productid' => 12345, 'stockamount' => 1.0, 'stockdescription' => 'description', 'stockdate' => '2022-02-02']],
                true,
            ],
        ];
    }

    /**
     * Tests that the correct arguments are passed to Acumulus::callApiFunction().
     *
     * @dataProvider argumentsPassed
     */
    public function testArgumentPassingToCallApiFunction(
        string $method,
        array $args,
        string $apiFunction,
        array $message,
        $needContract,
        array $parameters = []
    ): void {
        // To test the arguments passed to the protected callApiFunction()
        // method we mock it.
        $stub = $this->getMockBuilder(Acumulus::class)
            ->onlyMethods(['callApiFunction'])
            ->setConstructorArgs([self::getContainer(), self::getContainer()->getEnvironment(), self::getContainer()->getLog(),])
            ->getMock();
        $stub->expects($this->once())
            ->method('callApiFunction')
            ->with($this->equalTo($apiFunction), $this->equalTo($message), $this->equalTo($parameters), $this->equalTo($needContract));

        $stub->$method(... $args);
    }

    /**
     * Tests that the correct arguments are passed to ApiCommunicator::getUri
     * and that the correct query arguments are concatenated.
     *
     * @noinspection SpellCheckingInspection
     */
    public function testGetInvoicePdfUri(): void
    {
        $environment = self::getContainer()->getEnvironment()->toArray();
        $apiAddress = $environment['baseUri'] . '/' . $environment['apiVersion'];
        $acumulus = self::getContainer()->getAcumulusApiClient();
        self::assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN", $acumulus->getInvoicePdfUri('TOKEN'));
        self::assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN&invoicetype=1", $acumulus->getInvoicePdfUri('TOKEN', true));
        self::assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN&invoicetype=0", $acumulus->getInvoicePdfUri('TOKEN', false));
        self::assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN&gfx=1", $acumulus->getInvoicePdfUri('TOKEN', null, true));
        self::assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN&gfx=0", $acumulus->getInvoicePdfUri('TOKEN', null, false));
        self::assertSame(
            "$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN&invoicetype=1&gfx=0",
            $acumulus->getInvoicePdfUri('TOKEN', true, false)
        );
    }

    /**
     * Tests that the correct arguments are passed to ApiCommunicator::getUri
     * and that the correct query arguments are concatenated.
     */
    public function testGetPackingSlipPdfUri(): void
    {
        $environment = self::getContainer()->getEnvironment()->toArray();
        $apiAddress = $environment['baseUri'] . '/' . $environment['apiVersion'];
        self::assertSame(
            "$apiAddress/delivery/packing_slip_get_pdf.php?token=TOKEN",
            self::getContainer()->getAcumulusApiClient()->getPackingSlipPdfUri('TOKEN')
        );
        self::assertSame(
            "$apiAddress/delivery/packing_slip_get_pdf.php?token=TOKEN&gfx=1",
            self::getContainer()->getAcumulusApiClient()->getPackingSlipPdfUri('TOKEN', true)
        );
        self::assertSame(
            "$apiAddress/delivery/packing_slip_get_pdf.php?token=TOKEN&gfx=0",
            self::getContainer()->getAcumulusApiClient()->getPackingSlipPdfUri('TOKEN', false)
        );
    }
}
