<?php
/** @noinspection PhpStaticAsDynamicMethodCallInspection */

namespace Siel\Acumulus\Integration\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusRequest;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Severity;

class AcumulusTest extends TestCase
{
    /**
     * @var \Siel\Acumulus\ApiClient\Acumulus
     */
    protected /*Acumulus*/ $acumulusClient;

    protected function setUp(): void
    {
        // Using TestWebShop would give us a test HttpCommunicator, but we want
        // a real one here.
        $container = new Container('TestWebShop', 'nl');
        $apiCommunicator = new AcumulusRequest($container->getConfig(), $container->getLanguage(), $container->getLog());
        $this->acumulusClient = new Acumulus($apiCommunicator, $container, $container->getConfig());
    }

    public function responseKeysProvider(): array
    {
        return [
            'About' => ['getAbout', [], false, ['about', 'role', 'roleapi', 'roleid', 'rolenl']],
            'MyAcumulus' => ['getMyAcumulus', [], false, ['myaddress', 'mycity', 'mycompanyname', 'mycontactperson', 'mycontractcode', 'mycontractenddate', 'mydebt', 'myemail', 'myemailstatusid', 'myemailstatusreferenceid', 'myentries', 'myentriesleft', 'myiban', 'mymaxentries', 'mypostalcode', 'mysalutation', 'mysepamandatenr', 'mystatusid', 'mytelephone', 'myvatnumber']],
            'Accounts enabled' => ['getPicklistAccounts', [true], true, ['accountid', 'accountnumber', 'accountdescription', 'accountorderid', 'accountstatus', 'accounttypeid']],
            'ContactTypes' => ['getPicklistContactTypes', [], true, ['contacttypeid', 'contacttypename', 'contacttypenamenl']],
            'CostCenters' => ['getPicklistCostCenters', [], true, ['costcenterid', 'costcentername']],
            'InvoiceTemplates' => ['getPicklistInvoiceTemplates', [], true, ['invoicetemplateid', 'invoicetemplatename']],
            'CompanyTypes' => ['getPicklistCompanyTypes', [], true, ['companytypeid', 'companytypename', 'companytypenamenl']],
            'VatInfo' => ['getVatInfo', ['nl'], true, ['vattype', 'vatrate']],
            'ThresholdEuCommerce' => ['reportThresholdEuCommerce', [], false, ['year', 'threshold', 'nltaxed', 'reached']],
            'Products' => ['getPicklistProducts', [], true, ['productid', 'productnature', 'productdescription', 'producttagid', 'productcontactid', 'productprice', 'productvatrate', 'productsku', 'productstockamount', 'productean', 'producthash', 'productnotes']],
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

    public function vatInfoProvider(): array
    {
        return [
            'nl' => [['nl', '2015-01-01'], [['vattype' => 'reduced', 'vatrate' => '0.0000'],['vattype' => 'reduced', 'vatrate' => '6.0000'],['vattype' => 'normal', 'vatrate' => '21.0000']]],
            'nl-no-date' => [['nl'], [['vattype' => 'reduced', 'vatrate' => '0.0000'],['vattype' => 'reduced', 'vatrate' => '9.0000'],['vattype' => 'normal', 'vatrate' => '21.0000']]],
            'eu' => [['be', '2015-12-01'], [['vattype' => 'reduced', 'vatrate' => '6.0000'],['vattype' => 'reduced', 'vatrate' => '12.0000'],['vattype' => 'normal', 'vatrate' => '21.0000'],['vattype' => 'parked', 'vatrate' => '12.0000'],['vattype' => 'reduced', 'vatrate' => '0.0000']]],
            'eu-no-date' => [['be'], [['vattype' => 'reduced', 'vatrate' => '6.0000'],['vattype' => 'reduced', 'vatrate' => '12.0000'],['vattype' => 'normal', 'vatrate' => '21.0000'],['vattype' => 'parked', 'vatrate' => '12.0000'],['vattype' => 'reduced', 'vatrate' => '0.0000']]],
            'eu-wrong-date' => [['be', '2014-12-01'], []],
            'gb-eu' => [['gb', '2020-12-01'], [['vattype' => 'reduced', 'vatrate' => '0.0000'],['vattype' => 'reduced', 'vatrate' => '5.0000'],['vattype' => 'normal', 'vatrate' => '20.0000']]],
            'no-eu' => [['af', '2020-12-01'], []],
        ];
    }

    /**
     * @dataProvider vatInfoProvider
     */
    public function testGetVatInfo(array $args, array $expected)
    {
        $result = $this->acumulusClient->getVatInfo(... $args);
        $this->assertSame(Severity::Success, $result->getStatus());

        $vatRate  = array_column($expected, 'vatrate');
        $vatType = array_column($expected, 'vattype');
        array_multisort($vatRate, SORT_DESC, $vatType, SORT_ASC, $expected);

        $actual = $result->getResponse();
        $vatRate  = array_column($actual, 'vatrate');
        $vatType = array_column($actual, 'vattype');
        array_multisort($vatRate, SORT_DESC, $vatType, SORT_ASC, $actual);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Tests call to get VAT info with an invalid country code.
     */
    public function testGetVatInfoInvalidCountryCode()
    {
        $result = $this->acumulusClient->getVatInfo('ln', '2020-12-01');
        $this->assertSame(Severity::Error, $result->getStatus());
        $this->assertNotEmpty($result->getByCodeTag('AA6A45AA'));
    }

    /**
     * Tests call to get the threshold for 2021.
     */
    public function testReportThresholdEuCommerce()
    {
        $result = $this->acumulusClient->reportThresholdEuCommerce(2021);
        $this->assertSame(Severity::Success, $result->getStatus());
        $actual = $result->getResponse();
        $threshold = $actual['threshold'];
        $this->assertEquals(10000, $threshold);
    }

    /**
     * Tests call to report threshold EU commerce with an old year before it was introduced.
     */
    public function testReportThresholdEuCommerceOldYear()
    {
        $result = $this->acumulusClient->reportThresholdEuCommerce(2020);
        $this->assertSame(Severity::Error, $result->getStatus());
        $this->assertNotEmpty($result->getByCodeTag('AAC37EAA'));
    }

    /**
     * Tests call to report threshold EU commerce with a future year.
     */
    public function testReportThresholdEuCommerceFutureYear()
    {
        $result = $this->acumulusClient->reportThresholdEuCommerce(2099);
        $this->assertSame(Severity::Success, $result->getStatus());
        $actual = $result->getResponse();
        $threshold = $actual['threshold'];
        $this->assertEquals(10000, $threshold);
    }

    public function stockAddProvider(): array
    {
        $productId = 1833636;
        return [ // $productId, $quantity, $description, $date
            'buy' => [[$productId, -2, 'Bestelling 123', '2020-12-11'], ['productid' => $productId, 'stockamount' => 18]],
            'refund' => [[$productId, 2, 'Refund bestelling 123'], ['productid' => $productId, 'stockamount' => 20]]
        ];
    }

    /**
     * @dataProvider stockAddProvider
     */
    public function testStockAdd(array $args, array $expected)
    {
        $result = $this->acumulusClient->stockAdd(... $args);
        $this->assertSame(Severity::Success, $result->getStatus());


        $actual = $result->getResponse();
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
