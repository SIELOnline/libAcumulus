<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\ApiBehaviour\ApiClient;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\ApiClient\Acumulus;

/**
 * Tests the Acumulus class in combination with the Acumulus API, so we can
 * discover new fields, regions, etc.
 */
class AcumulusTest extends TestCase
{
    protected Container $container;
    protected Acumulus $acumulusClient;

    protected function setUp(): void
    {
        // Using TestWebShop would give us test classes, but we want real ones
        // here.
        $this->container = new Container('TestWebShop', 'nl');
        $this->acumulusClient = $this->container->getAcumulusApiClient();
    }

    /**
     * Kunnen we hier nog wat mee?
     *
     * @return array[]
     */
    public function argumentsPassedDeprecated(): array
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
            'SetPaymentStatus' => ['setPaymentStatus', ['TOKEN', Api::PaymentStatus_Paid, '2020-02-02'], 'invoices/invoice_paymentstatus_set', true, 'invoice', false, ['token' => 'TOKEN', 'paymentstatus' => Api::PaymentStatus_Paid, 'paymentdate' => '2020-02-02']],
            'Signup' => ['signup', [['companyname' => 'BR']], 'signup/signup', false, 'signup', false, ['signup' => ['companyname' => 'BR']]],
        ];
    }


    public function responseKeysProvider(): array
    {
        return [
            'About' => ['getAbout', [], false, ['about', 'role', 'roleapi', 'roleid', 'rolenl']],
            'MyAcumulus' => ['getMyAcumulus', [], false, ['myaddress', 'mycity', 'mycompanyname', 'mycontactperson', 'mycontractcode', 'mycontractenddate', 'mydebt', 'myemail', 'myemailstatusid', 'myemailstatusreferenceid', 'myentries', 'myentriesleft', 'myiban', 'mymaxentries', 'mypostalcode', 'mysalutation', 'mysepamandatenr', 'mystatusid', 'mysupport', 'mytelephone', 'myvatnumber']],
            'Accounts enabled' => ['getPicklistAccounts', [true], true, ['accountid', 'accountnumber', 'accountdescription', 'accountorderid', 'accountstatus', 'accounttypeid']],
            'CompanyTypes' => ['getPicklistCompanyTypes', [], true, ['companytypeid', 'companytypename', 'companytypenamenl']],
            'ContactTypes' => ['getPicklistContactTypes', [], true, ['contacttypeid', 'contacttypename', 'contacttypenamenl']],
            'CostCenters' => ['getPicklistCostCenters', [], true, ['costcenterid', 'costcentername']],
            'InvoiceTemplates' => ['getPicklistInvoiceTemplates', [], true, ['invoicetemplateid', 'invoicetemplatename']],
            'Products' => ['getPicklistProducts', [], true, ['productid', 'productnature', 'productdescription', 'producttagid', 'productcontactid', 'productprice', 'productvatrate', 'productsku', 'productstockamount', 'productean', 'producthash', 'productnotes']],
            'VatInfo' => ['getVatInfo', ['nl'], true, ['vattype', 'vatrate', 'countryregion']],
            'ThresholdEuCommerce' => ['reportThresholdEuCommerce', [], false, ['year', 'threshold', 'nltaxed', 'reached']],
        ];
    }

    /**
     * @dataProvider responseKeysProvider
     */
    public function testServicesKeysInResponse(string $method, array $args, bool $isList, array $expectedKeys): void
    {
        /** @var \Siel\Acumulus\ApiClient\AcumulusResult $result */
        $result = $this->acumulusClient->$method(... $args);
        $this->assertSame(Severity::Success, $result->getStatus());
        $response = $result->getMainAcumulusResponse();
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
            'nl' => [['nl', '2015-01-01'],
                [
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '1'],
                    ['vattype' => 'reduced', 'vatrate' => '6.0000', 'countryregion' => '1'],
                    ['vattype' => 'normal', 'vatrate' => '21.0000', 'countryregion' => '1'],
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '2'],
                    ['vattype' => 'normal', 'vatrate' => '21.0000', 'countryregion' => '2'],
                ],
            ],
            'nl-no-date' => [['nl'],
                [
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '1'],
                    ['vattype' => 'reduced', 'vatrate' => '9.0000', 'countryregion' => '1'],
                    ['vattype' => 'normal', 'vatrate' => '21.0000', 'countryregion' => '1'],
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '2'],
                    ['vattype' => 'reduced', 'vatrate' => '9.0000', 'countryregion' => '2'],
                    ['vattype' => 'normal', 'vatrate' => '21.0000', 'countryregion' => '2'],
                    ['vattype' => 'reduced', 'vatrate' => '-1.0000', 'countryregion' => '2'],
                ],
            ],
            'eu' => [['be', DateTimeImmutable::createFromFormat(Api::DateFormat_Iso, '2015-12-01')],
                [
                    ['vattype' => 'reduced', 'vatrate' => '6.0000', 'countryregion' => '2'],
                    ['vattype' => 'reduced', 'vatrate' => '12.0000', 'countryregion' => '2'],
                    ['vattype' => 'normal', 'vatrate' => '21.0000', 'countryregion' => '2'],
                    ['vattype' => 'parked', 'vatrate' => '12.0000', 'countryregion' => '2'],
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '2'],
                ],
            ],
            'eu-no-date' => [['be'],
                [
                    ['vattype' => 'reduced', 'vatrate' => '6.0000', 'countryregion' => '2'],
                    ['vattype' => 'reduced', 'vatrate' => '12.0000', 'countryregion' => '2'],
                    ['vattype' => 'normal', 'vatrate' => '21.0000', 'countryregion' => '2'],
                    ['vattype' => 'parked', 'vatrate' => '12.0000', 'countryregion' => '2'],
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '2'],
                ],
            ],
            'eu-wrong-date' => [['be', '2014-12-01'], []],
            'gb-eu' => [['gb', '2020-12-01'],
                [
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '2'],
                    ['vattype' => 'reduced', 'vatrate' => '5.0000', 'countryregion' => '2'],
                    ['vattype' => 'normal', 'vatrate' => '20.0000', 'countryregion' => '2'],
                ],
            ],
            'gb-no-eu' => [['gb', '2021-01-01'],
                [
                    ['vattype' => 'reduced', 'vatrate' => '0.0000', 'countryregion' => '3'],
                    ['vattype' => 'reduced', 'vatrate' => '5.0000', 'countryregion' => '3'],
                    ['vattype' => 'normal', 'vatrate' => '20.0000', 'countryregion' => '3'],
                ],
            ],
            'no-eu' => [['af', '2020-12-01'], []],
        ];
    }

    /**
     * @dataProvider vatInfoProvider
     */
    public function testGetVatInfo(array $args, array $expected): void
    {
        $result = $this->acumulusClient->getVatInfo(... $args);
        $this->assertSame(Severity::Success, $result->getStatus());

        $vatRate  = array_column($expected, 'vatrate');
        $vatType = array_column($expected, 'vattype');
        array_multisort($vatRate, SORT_DESC, $vatType, SORT_ASC, $expected);

        $actual = $result->getMainAcumulusResponse();
        $vatRate  = array_column($actual, 'vatrate');
        $vatType = array_column($actual, 'vattype');
        array_multisort($vatRate, SORT_DESC, $vatType, SORT_ASC, $actual);

        $this->assertSame($expected, $actual);
    }

    /**
     * Tests call to get VAT info with an invalid country code.
     */
    public function testGetVatInfoInvalidCountryCode(): void
    {
        $result = $this->acumulusClient->getVatInfo('ln', '2020-12-01');
        $this->assertSame(Severity::Error, $result->getStatus());
        $this->assertNotEmpty($result->getByCodeTag('AA6A45AA'));
    }

    /**
     * Tests call to get the threshold for 2021.
     */
    public function testReportThresholdEuCommerce(): void
    {
        $result = $this->acumulusClient->reportThresholdEuCommerce(2021);
        $this->assertSame(Severity::Success, $result->getStatus());
        $actual = $result->getMainAcumulusResponse();
        $threshold = $actual['threshold'];
        $this->assertEquals(10000, $threshold);
    }

    /**
     * Tests call to report threshold EU commerce with an old year before it was introduced.
     */
    public function testReportThresholdEuCommerceOldYear(): void
    {
        $result = $this->acumulusClient->reportThresholdEuCommerce(2020);
        $this->assertSame(Severity::Error, $result->getStatus());
        $this->assertNotEmpty($result->getByCodeTag('AAC37EAA'));
    }

    /**
     * Tests call to report threshold EU commerce with a future year.
     */
    public function testReportThresholdEuCommerceFutureYear(): void
    {
        $result = $this->acumulusClient->reportThresholdEuCommerce(2099);
        $this->assertSame(Severity::Success, $result->getStatus());
        $actual = $result->getMainAcumulusResponse();
        $threshold = $actual['threshold'];
        $this->assertEquals(10000, $threshold);
    }

    public function stockTransactionProvider(): array
    {
        $productId = 1833636;

        $stockTransaction1 = new StockTransaction();
        $stockTransaction1->productId = $productId;
        $stockTransaction1->stockAmount = -2.0;
        $stockTransaction1->stockDescription = 'Bestelling 123';

        $stockTransaction2 = new StockTransaction();
        $stockTransaction2->productId = $productId;
        $stockTransaction2->stockAmount = 2.0;
        $stockTransaction2->stockDescription = 'Refund bestelling 123';

        return [
            'buy' => [$stockTransaction1, ['productid' => $productId, 'stockamount' => 30.0]],
            'refund' => [$stockTransaction2, ['productid' => $productId, 'stockamount' => 32.0]]
        ];
    }

    /**
     * @dataProvider stockTransactionProvider
     */
    public function testStockTransaction(StockTransaction $stockTransaction, array $expected): void
    {
        $result = $this->acumulusClient->stockTransaction($stockTransaction);

        $this->assertSame(Severity::Success, $result->getStatus());
        $actual = $result->getMainAcumulusResponse();
        $this->assertEqualsCanonicalizing($expected, $actual);
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
