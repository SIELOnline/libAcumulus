<?php
namespace Siel\Acumulus\Integration\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\ApiCommunicator;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\HttpCommunicator;

class AcumulusTest extends TestCase
{
    /**
     * @var \Siel\Acumulus\ApiClient\Acumulus
     */
    protected $acumulusClient;

    protected function setUp(): void
    {
        // Using TestWebShop would give us a test HttpCommunicator, but we want
        // a real one here.
        $container = new Container('TestWebShop', 'nl');
        $httpCommunicator = new HttpCommunicator();
        $apiCommunicator = new ApiCommunicator($httpCommunicator, $container->getConfig(), $container->getLanguage(), $container->getLog());
        $this->acumulusClient = new Acumulus($apiCommunicator, $container, $container->getConfig());
    }

    public function responseKeysProvider()
    {
        return [
            'About' => ['getAbout', [], false, ['about', 'role', 'roleapi', 'roleid', 'rolenl']],
            'MyAcumulus' => ['getMyAcumulus', [], false, ['myaddress','mycity','mycompanyname','mycontactperson','mycontractcode','mycontractenddate','mydebt','myemail','myemailstatusid','myemailstatusreferenceid','myentries','myentriesleft','myiban','mymaxentries','mypostalcode','mysalutation','mysepamandatenr','mystatusid','mytelephone','myvatnumber']],
            'Accounts' => ['getPicklistAccounts', [], true, ['accountid', 'accountnumber', 'accountdescription','accounttypeid']],
            'ContactTypes' => ['getPicklistContactTypes', [], true, ['contacttypeid', 'contacttypename','contacttypenamenl']],
            'CostCenters' => ['getPicklistCostCenters', [], true, ['costcenterid','costcentername']],
            'InvoiceTemplates' => ['getPicklistInvoiceTemplates', [], true, ['invoicetemplateid','invoicetemplatename']],
            'CompanyTypes' => ['getPicklistCompanyTypes', [], true, ['companytypeid', 'companytypename', 'companytypenamenl']],
            'VatInfo' => ['getVatInfo', ['nl'], true, ['vattype','vatrate']],
        ];
    }

    /**
     * @dataProvider responseKeysProvider
     */
    public function testServicesKeysInResponse(string $method, array $args, bool $isList, array $expectedKeys)
    {
        $result = $this->acumulusClient->$method(... $args);
        $this->assertSame(Severity::Success, $result->getStatus());
        $response = $result->getResponse();
        if ($isList) {
            $this->assertIsArray($response);
            $this->assertNotEmpty($response);
            $singleResponse = reset($response);
            $this->assertIsInt(key($response));
        } else {
            $singleResponse = $response;
        }

        $this->assertIsArray($singleResponse);
        $this->assertNotEmpty($singleResponse);
        $this->assertEqualsCanonicalizing($expectedKeys, array_keys($singleResponse));
    }

    public function vatInfoProvider()
    {
        return [
            'nl' => [['nl', '2015-01-01'], [['vattype' => 'reduced','vatrate' => '6.0000'],['vattype' => 'normal','vatrate' => '21.0000']]],
            'nl-no-date' => [['nl'], [['vattype' => 'reduced','vatrate' => '9.0000'],['vattype' => 'normal','vatrate' => '21.0000']]],
            'eu' => [['be', '2015-12-01'], [['vattype' => 'reduced','vatrate' => '6.0000'],['vattype' => 'reduced','vatrate' => '12.0000'],['vattype' => 'normal','vatrate' => '21.0000'],['vattype' => 'parked','vatrate' => '12.0000'],['vattype' => 'reduced','vatrate' => '0.0000']]],
            'eu-no-date' => [['be'], [['vattype' => 'reduced','vatrate' => '6.0000'],['vattype' => 'reduced','vatrate' => '12.0000'],['vattype' => 'normal','vatrate' => '21.0000'],['vattype' => 'parked','vatrate' => '12.0000'],['vattype' => 'reduced','vatrate' => '0.0000']]],
            'eu-wrong-date' => [['be', '2014-12-01'], []],
            'non-eu' => [['af', '2020-12-01'], ['vattype','vatrate']],
        ];
    }

    /**
     * @dataProvider vatInfoProvider
     */
    public function testGetVatInfo(array $args, array $expected)
    {
        $result = $this->acumulusClient->getVatInfo(... $args);
        $this->assertSame(Severity::Success, $result->getStatus());

        $vatrate  = array_column($expected, 'vatrate');
        $vattype = array_column($expected, 'vattype');
        array_multisort($vatrate, SORT_DESC, $vattype, SORT_ASC, $expected);

        $actual = $result->getResponse();
        $vatrate  = array_column($actual, 'vatrate');
        $vattype = array_column($actual, 'vattype');
        array_multisort($vatrate, SORT_DESC, $vattype, SORT_ASC, $actual);

        $this->assertEquals($expected, $actual);
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
