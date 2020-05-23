<?php
namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Helpers\Container;

class AcumulusTest extends TestCase
{
    /**
     * @var \Siel\Acumulus\ApiClient\Acumulus
     */
    protected $acumulusClient;

    protected function setUp(): void
    {
        // Using TestWebShop gives us the test ApiCommunicator.
        $container = new Container('TestWebShop', 'nl');
        $this->acumulusClient = $container->getAcumulusApiClient();
    }

    public function argumentsPassed()
    {
        // See for the meaning of each entry the parameter list of testApiCalls.
        return [
            'About' => ['getAbout', [], 'general/general_about', true, 'general', false],
            'Accounts' => ['getPicklistAccounts', [], 'picklists/picklist_accounts', true, 'accounts', true],
            'ContactTypes' => ['getPicklistContactTypes', [], 'picklists/picklist_contacttypes', true, 'contacttypes', true],
            'CostCenters' => ['getPicklistCostCenters', [], 'picklists/picklist_costcenters', true, 'costcenters', true],
            'InvoiceTemplates' => ['getPicklistInvoiceTemplates', [], 'picklists/picklist_invoicetemplates', true, 'invoicetemplates', true],
            'VatInfo' => ['getVatInfo', ['nl'], 'lookups/lookup_vatinfo', true, 'vatinfo', true],
            'InvoiceAdd' => ['invoiceAdd', [['customer' => []]], 'invoices/invoice_add', true, 'invoice', false, ['customer' => []]],
            'ConceptInfo' => ['getConceptInfo', [12345], 'invoices/invoice_concept_info', true, 'concept', false, ['conceptid' => 12345]],
            'Entry' => ['getEntry', [12345], 'entry/entry_info', true, 'entry', false, ['entryid' => 12345]],
            'SetDeleteStatus1' => ['setDeleteStatus', [12345, true], 'entry/entry_deletestatus_set', true, 'entry', false, ['entryid' => 12345, 'entrydeletestatus' => 1]],
            'SetDeleteStatus0' => ['setDeleteStatus', [12345, false], 'entry/entry_deletestatus_set', true, 'entry', false, ['entryid' => 12345, 'entrydeletestatus' => 0]],
            'GetPaymentStatus' => ['getPaymentStatus', ['TOKEN'], 'invoices/invoice_paymentstatus_get', true, 'invoice', false, ['token' => 'TOKEN']],
            'SetPaymentStatus' => ['setPaymentStatus', ['TOKEN', API::PaymentStatus_Paid, '2020-02-02'], 'invoices/invoice_paymentstatus_set', true, 'invoice', false, ['token' => 'TOKEN', 'paymentstatus' => API::PaymentStatus_Paid, 'paymentdate' => '2020-02-02']],
            'Signup' => ['signup', [['companyname' => 'BR']], 'signup/signup', false, 'signup', false, ['signup' => ['companyname' => 'BR']]],
        ];
    }

    /**
     * Tests that the correct arguments are passed to ApiCommunicator::callApiFunction.
     *
     * @dataProvider argumentsPassed
     */
    public function testApiCalls(string $method, array $args, string $apiFunction, $needContract, string $mainResponseKey, bool $isList, array $message = null)
    {
        /** @var \Siel\Acumulus\ApiClient\Result $result */
        $result = $this->acumulusClient->$method(... $args);
        $this->assertSame($apiFunction, $result->getByCodeTag('apiFunction')->getText());
        $this->assertSame($needContract ? 'true' : 'false', $result->getByCodeTag('needContract')->getText());
        $this->assertSame($mainResponseKey, $result->getByCodeTag('mainResponseKey')->getText());
        $this->assertSame($isList ? 'true' : 'false', $result->getByCodeTag('isList')->getText());
        if (!empty($message)) {
            $this->assertSame(json_encode($message), $result->getByCodeTag('message')->getText());
        }
    }

    /**
     * Tests that the correct arguments are passed to ApiCommunicator::getUri
     * and that the correct query arguments are concatenated.
     */
    public function testGetInvoicePdfUri()
    {
        $this->assertSame('invoices/invoice_get_pdf?token=TOKEN', $this->acumulusClient->getInvoicePdfUri('TOKEN'));
        $this->assertSame('invoices/invoice_get_pdf?token=TOKEN', $this->acumulusClient->getInvoicePdfUri('TOKEN', true));
        $this->assertSame('invoices/invoice_get_pdf?token=TOKEN&gfx=0', $this->acumulusClient->getInvoicePdfUri('TOKEN', false));
    }

    /**
     * Tests that the correct arguments are passed to ApiCommunicator::getUri
     * and that the correct query arguments are concatenated.
     */
    public function testGetPackingSlipUri()
    {
        $this->assertSame('delivery/packing_slip_get_pdf?token=TOKEN', $this->acumulusClient->getPackingSlipUri('TOKEN'));
    }
}
