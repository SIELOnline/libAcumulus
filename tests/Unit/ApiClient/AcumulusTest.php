<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\Helpers\Container;

class AcumulusTest extends TestCase
{

    /**
     * @var object|\Siel\Acumulus\ApiClient\Result
     */
    private $result;

    /**
     * @var \Siel\Acumulus\Helpers\Container
     */
    private $container;

    protected function setUp(): void
    {
        // Use the TestWebShop test doubles.
        $this->container = new Container('TestWebShop\TestDoubles', 'nl');
        $this->acumulusClient = $this->container->getAcumulusApiClient();
        $this->result = $this->container->getResult();
    }

    /**
     * Each data array consists of:
     * 0: method (string): the method to call.
     * 1: args (array): the args to pass to the (public) method.
     * 2: apiFunction (string): the api function to call.
     * 3: needContract (bool): whether authorisation is required.
     * 4: message (array): submit as expected by Acumulus, created from args.
     *
     * @return array[]
     */
    public function argumentsPassed(): array
    {
        // See for the meaning of each entry the parameter list of testApiCalls.
        return [
            'About' => ['getAbout', [], 'general/general_about', true, []],
            'MyAcumulus' => ['getMyAcumulus', [], 'general/my_acumulus', true, []],
            'Accounts' => ['getPicklistAccounts', [], 'picklists/picklist_accounts', true, ['accountstatus' => 1]],
            'CompanyTypes' => ['getPicklistCompanyTypes', [], 'picklists/picklist_companytypes', false, []],
            'ContactTypes' => ['getPicklistContactTypes', [], 'picklists/picklist_contacttypes', true, []],
            'CostCenters' => ['getPicklistCostCenters', [], 'picklists/picklist_costcenters', true, []],
            'InvoiceTemplates' => ['getPicklistInvoiceTemplates', [], 'picklists/picklist_invoicetemplates', true, []],
            'Products' => ['getPicklistProducts', [], 'picklists/picklist_products', true, []],
            'ProductsFilter' => ['getPicklistProducts', ['test'], 'picklists/picklist_products', true, ['filter' => 'test']],
            'ProductsTag' => ['getPicklistProducts', [null, 0], 'picklists/picklist_products', true, ['producttagid' => 0]],
            'ProductsSegment' => ['getPicklistProducts', [null, null, 40, 10], 'picklists/picklist_products', true, ['offset' => 40, 'rowcount' => 10]],
            'VatInfo' => ['getVatInfo', ['nl'], 'lookups/lookup_vatinfo', true, ['vatcountry' => 'nl', 'vatdate' => date(Api::DateFormat_Iso)]],
            'EuThreshold' => ['reportThresholdEuCommerce', [], 'reports/report_threshold_eu_ecommerce', true, []],
            'EuThresholdYear' => ['reportThresholdEuCommerce', [2021], 'reports/report_threshold_eu_ecommerce', true, ['year' => 2021]],
            'InvoiceAdd' => ['invoiceAdd', [['customer' => []]], 'invoices/invoice_add', true, ['customer' => []]],
            'ConceptInfo' => ['getConceptInfo', [12345], 'invoices/invoice_concept_info', true, ['conceptid' => 12345]],
            'Entry' => ['getEntry', [12345], 'entry/entry_info', true, ['entryid' => 12345]],
            'SetDeleteStatus1' => ['setDeleteStatus', [12345, true], 'entry/entry_deletestatus_set', true, ['entryid' => 12345, 'entrydeletestatus' => 1]],
            'SetDeleteStatus0' => ['setDeleteStatus', [12345, false], 'entry/entry_deletestatus_set', true, ['entryid' => 12345, 'entrydeletestatus' => 0]],
            'GetPaymentStatus' => ['getPaymentStatus', ['TOKEN'], 'invoices/invoice_paymentstatus_get', true, ['token' => 'TOKEN']],
            'SetPaymentStatus' => ['setPaymentStatus', ['TOKEN', Api::PaymentStatus_Paid, '2020-02-02'], 'invoices/invoice_paymentstatus_set', true, ['token' => 'TOKEN', 'paymentstatus' => Api::PaymentStatus_Paid, 'paymentdate' => '2020-02-02']],
            'emailInvoice' => ['emailInvoiceAsPdf', ['TOKEN', ['emailto' => 'test@example.com']], 'invoices/invoice_mail', true, ['token' => 'TOKEN', 'emailaspdf' => ['emailto' => 'test@example.com']]],
            'emailInvoiceReminder' => ['emailInvoiceAsPdf', ['TOKEN', ['emailto' => 'test@example.com'], Api::Email_Reminder], 'invoices/invoice_mail', true, ['token' => 'TOKEN', 'invoicetype' => Api::Email_Reminder, 'emailaspdf' => ['emailto' => 'test@example.com']]],
            'emailInvoiceNotes' => ['emailInvoiceAsPdf', ['TOKEN', ['emailto' => 'test@example.com'], null, 'my notes'], 'invoices/invoice_mail', true, ['token' => 'TOKEN', 'emailaspdf' => ['emailto' => 'test@example.com'], 'invoicenotes' => 'my notes']],
            'Signup' => ['signup', [['companyname' => 'BR']], 'signup/signup', false, ['signup' => ['companyname' => 'BR']]],
            'stockAdd' => ['stockAdd', [12345, 1, 'description', '2022-02-02'], 'stock/stock_add', true, ['stock' => ['productid' => 12345, 'stockamount' => 1, 'stockdescription' => 'description', 'stockdate' => '2022-02-02']]],
        ];
    }

    /**
     * Tests that the correct arguments are passed to Acumulus::callApiFunction().
     *
     * @dataProvider argumentsPassed
     */
    public function testArgumentPassingToCallApiFunction(string $method, array $args, string $apiFunction, $needContract, array $message)
    {
        // To test the arguments passed to the protected callApiFunction()
        // method we mock it.
        $stub = $this->getMockBuilder(Acumulus::class)
            ->onlyMethods(['callApiFunction'])
            ->setConstructorArgs([$this->container, $this->container->getConfig()])
            ->getMock();
        $stub->expects($this->once())
            ->method('callApiFunction')
            ->with(
                $this->equalTo($apiFunction),
                $this->equalTo($message),
                $this->equalTo($needContract)
            )
            ->willReturn($this->result);

        $stub->$method(... $args);
    }

    /**
     * Tests that the correct arguments are passed to ApiCommunicator::getUri
     * and that the correct query arguments are concatenated.
     */
    public function testGetInvoicePdfUri()
    {
        $environment = $this->container->getConfig()->getEnvironment();
        $apiAddress = $environment['baseUri'] . '/' . $environment['apiVersion'];
        $this->assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN", $this->acumulusClient->getInvoicePdfUri('TOKEN'));
        $this->assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN", $this->acumulusClient->getInvoicePdfUri('TOKEN', true));
        $this->assertSame("$apiAddress/invoices/invoice_get_pdf.php?token=TOKEN&gfx=0", $this->acumulusClient->getInvoicePdfUri('TOKEN', false));
    }

    /**
     * Tests that the correct arguments are passed to ApiCommunicator::getUri
     * and that the correct query arguments are concatenated.
     */
    public function testGetPackingSlipUri()
    {
        $environment = $this->container->getConfig()->getEnvironment();
        $apiAddress = $environment['baseUri'] . '/' . $environment['apiVersion'];
        $this->assertSame("$apiAddress/delivery/packing_slip_get_pdf.php?token=TOKEN", $this->acumulusClient->getPackingSlipUri('TOKEN'));
    }
}
