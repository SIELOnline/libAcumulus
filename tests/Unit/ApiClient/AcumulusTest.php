<?php
namespace Siel\Acumulus\Unit\ApiClient;

use PHPUnit\Framework\TestCase;
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
        return [
            'About' => ['getAbout', [], 'general/general_about', true],
            'Accounts' => ['getPicklistAccounts', [], 'picklists/picklist_accounts', true],
            'ContactTypes' => ['getPicklistContactTypes', [], 'picklists/picklist_contacttypes', true],
            'CostCenters' => ['getPicklistCostCenters', [], 'picklists/picklist_costcenters', true],
            'InvoiceTemplates' => ['getPicklistInvoiceTemplates', [], 'picklists/picklist_invoicetemplates', true],
            'VatInfo' => ['getVatInfo', ['nl'], 'lookups/lookup_vatinfo', true],
        ];
    }

    /**
     * @dataProvider argumentsPassed
     */
    public function testServices(string $method, array $args, string $apiFunction, $needContract, array $message = null)
    {
        /** @var \Siel\Acumulus\ApiClient\Result $result */
        $result = $this->acumulusClient->$method(... $args);
        $this->assertSame($result->getByCodeTag('apiFunction')->getText(), $apiFunction);
        $this->assertSame($result->getByCodeTag('needContract')->getText(), $needContract ? 'true' : 'false');
    }

/*
    public function testInvoiceAdd()
    {

    }

    public function testGetConceptInfo()
    {

    }

    public function testGetEntry()
    {

    }

    public function testSetDeleteStatus()
    {

    }

    public function testGetPaymentStatus()
    {

    }

    public function testSetPaymentStatus()
    {

    }

    public function testEmailInvoiceAsPdf()
    {

    }

    public function testSignUp()
    {

    }

    public function testGetInvoicePdfUri()
    {

    }

    public function testGetPackingSlipUri()
    {

    }
*/
}
