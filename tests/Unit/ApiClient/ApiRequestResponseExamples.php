<?php
/**
 * @noinspection PropertyCanBeStaticInspection
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\ApiClient;

use Siel\Acumulus\Data\BasicSubmit;
use Siel\Acumulus\Data\Connector;
use Siel\Acumulus\Data\Contract;
use Siel\Acumulus\Fld;

/**
 * This class defines a list of realistic request and response sets.
 *
 * The submit part in the examples is still all lowercase although that is no longer
 * necessary, it can be camel cased as our {@see \Siel\Acumulus\Data\AcumulusObject}s.
 */
class ApiRequestResponseExamples
{
    /**
     * Singleton pattern: single instance
     */
    private static ApiRequestResponseExamples $instance;

    /**
     * Singleton pattern: returns the instance.
     */
    public static function getInstance(): static
    {
        return static::$instance ?? (static::$instance = new ApiRequestResponseExamples());
    }

    /**
     * Singleton pattern: private contructor
     */
    private function __construct()
    {
    }

    private array $sets = [
        'accounts' => [
            'needContract' => true,
            'submit' => [],
            'response body' => '{"accounts":{"account":[{"accountid":"70582","accountnumber":"4911764","accountdescription":"Giro","accounttypeid":"1"},{"accountid":"70583","accountnumber":{},"accountdescription":"Kas","accounttypeid":"1"},{"accountid":"147659","accountnumber":"123456789","accountdescription":{},"accounttypeid":"1"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'accounts',
            'isList' => true,
            'main response' => [
                ['accountid' => '70582', 'accountnumber' => '4911764', 'accountdescription' => 'Giro', 'accounttypeid' => '1'],
                ['accountid' => '70583', 'accountnumber' => [], 'accountdescription' => 'Kas', 'accounttypeid' => '1'],
                ['accountid' => '147659', 'accountnumber' => '123456789', 'accountdescription' => [], 'accounttypeid' => '1'],
            ],
        ],

        'costcenters' => [
            'needContract' => true,
            'submit' => [],
            'resonse body' => '{"costcenters":{"costcenter":[{"costcenterid":"48663","costcentername":"Algemeen"},{"costcenterid":"56074","costcentername":"kostenplaats 1"},{"costcenterid":"56075","costcentername":"kostenplaats 2"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'costcenters',
            'isList' => true,
            'main response' => [
                ['costcenterid' => '48663', 'costcentername' => 'Algemeen'],
                ['costcenterid' => '56074', 'costcentername' => 'kostenplaats 1'],
                ['costcenterid' => '56075', 'costcentername' => 'kostenplaats 2'],
            ],
        ],

        'no-contract' => [
            'needContract' => false,
            'submit' => [
                'vatdate' => '2020-02-05',
                'vatcountry' => 'nl',
            ],
            'http status code' => 403,
            'response body' => '{"errors":{"error":{"code":"403 Forbidden","codetag":"AF1001MCS","message":"Verplichte contract sectie ontbreekt"},"count_errors":"1"},"warnings":{"count_warnings":"0"},"status":"1"}',
            'mainResponseKey' => 'costcenters',
            'isList' => true,
            'main response' => [],
        ],

        'vatinfo' => [
            'needContract' => false,
            'submit' => [
                'vatdate' => '2020-02-05',
                'vatcountry' => 'nl',
            ],
            'response body' => '{"vatinfo":{"vat":[{"vattype":"normal","vatrate":"21.0000"},{"vattype":"reduced","vatrate":"9.0000"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'vatinfo',
            'isList' => true,
            'main response' => [['vattype' => 'normal', 'vatrate' => '21.0000'], ['vattype' => 'reduced', 'vatrate' => '9.0000']],
        ],

        'vatinfo-empty-return' => [
            'needContract' => false,
            'submit' => [
                'vatdate' => '2014-01-01',
                'vatcountry' => 'fr',
            ],
            'response body' => '{"vatinfo":{},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'vatinfo',
            'isList' => true,
            'main response' => [],
        ],

        'invoice-add' => [
            'needContract' => true,
            'submit' => [
                'customer' => [
                    'type' => '3',
                    'contactstatus' => '0',
                    'companyname1' => 'Les Camélias',
                    'companyname2' => 'Les Camélias',
                    'fullname' => 'Erwin Derksen',
                    'salutation' => 'Beste Erwin',
                    'address1' => '6 avenue de Clermont',
                    'postalcode' => '63240',
                    'city' => 'Le Mont-Dore',
                    'countrycode' => 'FR',
                    'country' => 'Frankrijk',
                    'telephone' => '1234567890',
                    'email' => 'test@bexample.com',
                    'overwriteifexists' => '1',
                    'mark' => '::1',
                    'invoice' => [
                        'concept' => '0',
                        'issuedate' => '2020-02-05',
                        'costcenter' => '48663',
                        'accountnumber' => '70582',
                        'paymentstatus' => '2',
                        'paymentdate' => '2020-04-20',
                        'description' => 'Order LC202040',
                        'template' => '52884',
                        'meta-currency' => 'EUR',
                        'meta-currency-rate' => '1',
                        'meta-currency-do-convert' => 'false',
                        'meta-payment-method' => 'bacs',
                        'meta-invoice-amountinc' => '99.16',
                        'meta-invoice-vatamount' => '16.53',
                        'meta-invoice-amount' => '82.63',
                        'meta-invoice-calculated' => 'meta-invoice-amount',
                        'line' => [
                            0 => [
                                'product' => 'Ninja Silhouette (Naam: Erwin)',
                                'nature' => 'Product',
                                'meta-id' => '401',
                                'quantity' => '1',
                                'unitprice' => '78.503443333333',
                                'unitpriceinc' => '94.204132',
                                'meta-unitpriceinc-precision' => '0.001',
                                'meta-recalculate-price' => 'unitprice',
                                'vatrate' => '20.0000',
                                'meta-vatrate-min' => '19.989776606158',
                                'meta-vatrate-max' => '20.00811942888',
                                'vatamount' => '15.7',
                                'meta-unitprice-precision' => '0.01',
                                'meta-vatamount-precision' => '0.01',
                                'meta-vatrate-source' => 'completor-range',
                                'meta-vatclass-id' => 'digital-goods',
                                'meta-vatrate-lookup' => '[20]',
                                'meta-vatrate-lookup-label' => '["VAT"]',
                                'meta-sub-type' => 'order-item',
                                'meta-vatrate-range-matches' => '[{"vatrate":"20.0000","vattype":6}]',
                                'meta-children-merged' => '1',
                                'meta-recalculate-old-price' => '78.504132',
                                'meta-did-recalculate' => 'true',
                                'meta-vattypes-possible' => '6',
                            ],
                            1 => [
                                'product' => 'Vast Tarief',
                                'unitprice' => '4.1322',
                                'quantity' => '1',
                                'vatrate' => '20.0000',
                                'meta-vatrate-min' => '19.938056523422',
                                'meta-vatrate-max' => '20.234291799787',
                                'vatamount' => '0.83',
                                'meta-unitprice-precision' => '0.001',
                                'meta-vatamount-precision' => '0.01',
                                'meta-vatrate-source' => 'completor-range',
                                'meta-vatclass-id' => 'digital-goods',
                                'meta-vatrate-lookup' => '["20"]',
                                'meta-vatrate-lookup-label' => '["VAT"]',
                                'meta-vatrate-lookup-source' => 'shipping line taxes',
                                'meta-sub-type' => 'shipping',
                                'nature' => 'Service',
                                'meta-vatrate-range-matches' => '[{"vatrate":"20.0000","vattype":6}]',
                                'unitpriceinc' => '4.95864',
                                'meta-fields-calculated' => '["unitPriceInc"]',
                                'meta-vattypes-possible' => '6',
                            ],
                        ],
                        'meta-lines-amount' => '82.635643333333',
                        'meta-lines-amountinc' => '99.162772',
                        'meta-lines-vatamount' => '16.53',
                        'vattype' => '6',
                        'meta-vattypes-possible-invoice' => '1,6',
                        'meta-vattypes-possible-lines-intersection' => '6',
                        'meta-vattypes-possible-lines-union' => '6',
                    ],
                ],
            ],
            'response body' => '{"invoice":{"invoicenumber":"20240016","token":"lc9gfgYN8bQQHnIV99r4jmDraKhQoeIj","entryid":"55393014","contactid":"9326320","conceptid":[]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'invoice',
            'isList' => false,
            'main response' => [
                'invoicenumber' => '20240016',
                'token' => 'lc9gfgYN8bQQHnIV99r4jmDraKhQoeIj',
                'entryid' => '55393014',
                'contactid' => '9326320',
                'conceptid' => [],
            ],
        ],

        'invoice-add-concept' => [
            'needContract' => true,
            'submit' => [
                'customer' => [
                    'type' => 3,
                    'vattypeid' => 1,
                    'contactyourid' => '5',
                    'contactstatus' => 1,
                    'fullname' => 'Consument België',
                    'postalcode' => '1000',
                    'city' => 'Antwerpen',
                    'countrycode' => 'BE',
                    'country' => 'België',
                    'email' => 'consument.belgie@example.com',
                    'overwriteifexists' => 1,
                    'invoice' => [
                        'concept' => 1,
                        'meta-source-type' => 'Order',
                        'meta-source-id' => 8,
                        'issuedate' => '2024-06-07',
                        'costcenter' => 48663,
                        'accountnumber' => 70582,
                        'paymentstatus' => 1,
                        'description' => 'order 8',
                        'template' => 39851,
                        'meta-currency' => "{'currency':'EUR','rate':1.0,'doConvert':false}",
                        'meta-totals' => "{'amountEx':200.0,'amountInc':243.05,'amountVat':43.05,'vatBreakdown':{'BE High':'43.0500'},'calculated':'amountEx'}",
                        'line' => [
                            [
                                'product' => 'Samsung SyncMaster 941BW (Product 6)',
                                'nature' => 'Product',
                                'unitprice' => '200.0000',
                                'vatamount' => '42.0000',
                                'quantity' => '1',
                                'vatrate' => '21.0000',
                                'meta-vatrate-min' => 20.999661200949,
                                'meta-vatrate-max' => 21.000338800949,
                                'meta-unitprice-precision' => 0.001,
                                'meta-vatamount-precision' => 0.001,
                                'meta-vatrate-source' => 'completor-range',
                                'meta-vatclass-id' => '9',
                                'meta-vatclass-name' => 'High',
                                'meta-vatrate-lookup' => "['21.0000']",
                                'meta-vatrate-lookup-label' => "['BE High']",
                                'meta-line-type' => 'order-item',
                                'meta-vatrate-range-matches' => "[{'vatrate':21.0,'vattype':6}]",
                                'unitpriceinc' => 242,
                                'meta-fields-calculated' => "['unitpriceinc']",
                            ],
                            [
                                'product' => 'Flat Shipping Rate',
                                'quantity' => 1,
                                'unitprice' => '5.0000',
                                'vatrate' => '21.0000',
                                'meta-vatrate-source' => 'completor-lookup',
                                'meta-strategy-split' => false,
                                'meta-vatclass-id' => '9',
                                'meta-vatclass-name' => 'High',
                                'meta-vatrate-lookup' => "['21.0000']",
                                'meta-vatrate-lookup-label' => "['BE High']",
                                'meta-line-type' => 'shipping',
                                'meta-vatrate-lookup-matches' => "[{'vatrate':21.0,'vattype':6}]",
                                'unitpriceinc' => 6.05,
                                'meta-fields-calculated' => "['unitpriceinc','vatamount (from vatrate)']",
                                'nature' => 'Product',
                                'vatamount' => 1.05,
                            ],
                            [
                                'product' => 'Gift Certificate (EUR5OFF)',
                                'quantity' => 1,
                                'unitpriceinc' => '-5.0000',
                                'vatrate' => 0,
                                'meta-vatrate-source' => 'exact-0,corrected-no-vat',
                                'meta-line-type' => 'voucher',
                                'unitprice' => -5.0505050505051,
                                'meta-fields-calculated' => "['unitprice','vatamount (from vatrate)']",
                                'nature' => 'Product',
                                'vatamount' => 0,
                            ],
                        ],
                        'meta-lines-amount' => 199.94949494949,
                        'meta-lines-amountinc' => 243.05,
                        'meta-lines-vatamount' => 43.05,
                        'meta-warning' => '810: The invoice total does not match with the lines total. The amount (ex. vat) differs with €0.05. The invoice has been saved as concept. Check and correct the invoice in Acumulus.',
                        'vattype' => 6,
                        'meta-vattype-source' => 'Completor::checkForKnownVatType: only 1 possible vat type',
                    ],
                ],
            ],
            'response body' => '{"invoice":{"invoicenumber":"20240018","token":[],"entryid":[],"contactid":"9978102","conceptid":"1004704"},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'invoice',
            'isList' => false,
            'main response' => [
                'invoicenumber' => '20240018',
                'token' => [],
                'entryid' => [],
                'contactid' => '9978102',
                'conceptid' => '1004704',
            ],
        ],

        'signup' => [
            'needContract' => false,
            'submit' => [
                Fld::CompanyTypeId => 1,
                Fld::CompanyName => 'My Company',
                Fld::FullName => 'Smith',
                Fld::LoginName => 'John',
                Fld::Gender => 'M',
                Fld::Address => 'Straat 5',
                Fld::PostalCode => '1234 AB',
                Fld::City => 'Amsterdam',
                Fld::Email => 'john.doe@example.com',
            ],
            'response body' => '{"signup:":{"contractcode":"123456","contractloginname":"myuser","contractpassword":"mysecret","contractstartdate":"2022-02-22","contractenddate":"","contractapiuserloginname":"myapiuser","contractapiuserpassword":"mysecret"},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'signup',
            'isList' => false,
            'main response' => [
                'contractcode' => '123456',
                'contractloginname' => 'myuser',
                'contractpassword' => 'mysecret',
                'contractstartdate' => '2022-02-22',
                'contractenddate' => '',
                'contractapiuserloginname' => 'myapiuser',
                'contractapiuserpassword' => 'mysecret',
            ],
        ],

        'products' => [
            'needContract' => true,
            'submit' => ['filter' => 'TESTSKU'],
            'response body' => '{"products": {
                    "product": [
                        {"productid": "1833636", "productnature": "1", "productdescription": "t-shirt blauw", "producttagid": "0", "productcontactid": [], "productprice": "20.6612", "productvatrate": "21.00", "productsku": "TESTSKU", "productean": [], "productstockamount": "32.00", "producthash": "amrANtCVF7JXq4LN1etWuJzVAG7a2rGOTaXzbMBYI38so3dj", "productnotes": [] },
                        {"productid": "1833637", "productnature": "1", "productdescription": "t-shirt groen", "producttagid": "0", "productcontactid": [], "productprice": "20.6612", "productvatrate": "21.00", "productsku": "TESTSKU-GRO", "productean": [], "productstockamount": [], "producthash": "eIlLlg2Dp0ZHM4LGiF1RQ5oII9KfwWzpRus0792A7fxCxQlk", "productnotes": [] },
                        {"productid": "1833638", "productnature": "1", "productdescription": "t-shirt rood", "producttagid": "0", "productcontactid": [], "productprice": "20.6612", "productvatrate": "21.00", "productsku": "TESTSKU", "productean": [], "productstockamount": [], "producthash": "2dn66ZX7m8c2KPQfqT3v05hYZIg8U5hiSggFQYmH5HxorYEH", "productnotes": [] },
                        {"productid": "1833639", "productnature": "1", "productdescription": "t-shirt zwart", "producttagid": "0", "productcontactid": [], "productprice": "20.6612", "productvatrate": "21.00", "productsku": [], "productean": [], "productstockamount": [], "producthash": "WhlQ1NwoW58fSA9o3rHccBwlpa8yHZ95eLPqaDTrbNsuWm7h", "productnotes": [] },
                        {"productid": "1833640", "productnature": "1", "productdescription": "trui blauw", "producttagid": "0", "productcontactid": [], "productprice": "41.3224", "productvatrate": "21.00", "productsku": [], "productean": [], "productstockamount": [], "producthash": "YBl7qaN7Pw3fDKI2HeVARfDJ340rGdcLDM53Sw0eAViVDi5R", "productnotes": [] }
                    ]
                },
                "errors": {"count_errors": "0"},
                "warnings": {"count_warnings": "0"},
                "status": "0"
            }',
            'mainResponseKey' => 'products',
            'isList' => true,
            'main response' => [
                [
                    'productid' => '1833636',
                    'productnature' => '1',
                    'productdescription' => 't-shirt blauw',
                    'producttagid' => '0',
                    'productcontactid' => [],
                    'productprice' => '20.6612',
                    'productvatrate' => '21.00',
                    'productsku' => [],
                    'productean' => 'TESTSKU',
                    'productstockamount' => '32.00',
                    'producthash' => 'amrANtCVF7JXq4LN1etWuJzVAG7a2rGOTaXzbMBYI38so3dj',
                    'productnotes' => [],
                ],
                [
                    'productid' => '1833637',
                    'productnature' => '1',
                    'productdescription' => 't-shirt groen',
                    'producttagid' => '0',
                    'productcontactid' => [],
                    'productprice' => '20.6612',
                    'productvatrate' => '21.00',
                    'productsku' => 'TESTSKU-GRO',
                    'productean' => [],
                    'productstockamount' => [],
                    'producthash' => 'eIlLlg2Dp0ZHM4LGiF1RQ5oII9KfwWzpRus0792A7fxCxQlk',
                    'productnotes' => [],
                ],
                [
                    'productid' => '1833638',
                    'productnature' => '1',
                    'productdescription' => 't-shirt rood',
                    'producttagid' => '0',
                    'productcontactid' => [],
                    'productprice' => '20.6612',
                    'productvatrate' => '21.00',
                    'productsku' => 'TESTSKU',
                    'productean' => [],
                    'productstockamount' => [],
                    'producthash' => '2dn66ZX7m8c2KPQfqT3v05hYZIg8U5hiSggFQYmH5HxorYEH',
                    'productnotes' => [],
                ],
                [
                    'productid' => '1833639',
                    'productnature' => '1',
                    'productdescription' => 't-shirt zwart',
                    'producttagid' => '0',
                    'productcontactid' => [],
                    'productprice' => '20.6612',
                    'productvatrate' => '21.00',
                    'productsku' => [],
                    'productean' => [],
                    'productstockamount' => [],
                    'producthash' => 'WhlQ1NwoW58fSA9o3rHccBwlpa8yHZ95eLPqaDTrbNsuWm7h',
                    'productnotes' => [],
                ],
                [
                    'productid' => '1833640',
                    'productnature' => '1',
                    'productdescription' => 'trui blauw',
                    'producttagid' => '0',
                    'productcontactid' => [],
                    'productprice' => '41.3224',
                    'productvatrate' => '21.00',
                    'productsku' => [],
                    'productean' => [],
                    'productstockamount' => [],
                    'producthash' => 'YBl7qaN7Pw3fDKI2HeVARfDJ340rGdcLDM53Sw0eAViVDi5R',
                    'productnotes' => [],
                ],
            ],
        ],
        'stock-transaction' => [
            'needContract' => true,
            'submit' => ['productid' => 1833642, 'stockamount' => -2.0, 'stockDescription' => 'Bestelling 123',],
            'response body' => '{"stock":{"stockamount":"30.00","productid":"1833642"},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'mainResponseKey' => 'stock',
            'isList' => false,
            'main response' => ['stockamount' => '30.00', 'productid' => '1833642'],
        ],
        'stock-transaction-404' => [
            'needContract' => true,
            'submit' => ['productid' => 123, 'stockamount' => -2, 'stockdescription' => 'Bestelling 123', 'stockdate' => '2024-11-19'],
            'http status code' => 404,
            'response body' => '{"productid":"123","errors":{"error":{"code":"404 Not Found","codetag":"AA5B85AA","message":"Ongeldig productid"},"count_errors":"1"},"warnings":{"count_warnings":"0"},"status":"1"}',
            'mainResponseKey' => 'stock',
            'isList' => false,
            'main response' => ['productid' => 123],
        ],
    ];
    private array $options = [];

    public function setOptions(array $options): void
    {
        $this->options = $options + $this->options;
    }

    public function needContract(string $key): bool
    {
        return $this->sets[$key]['needContract'];
    }

    public function getSubmit(string $key): array
    {
        return $this->sets[$key]['submit'];
    }

    public function getHttpStatusCode(string $key): int
    {
        return $this->sets[$key]['http status code'] ?? 200;
    }

    public function getResponseBody(string $key): string
    {
        return $this->sets[$key]['response body'];
    }

    public function getMainResponseKey(string $key): string
    {
        return $this->sets[$key]['mainResponseKey'];
    }

    public function isList(string $key): bool
    {
        return $this->sets[$key]['isList'];
    }

    public function getMainResponse(string $key): array
    {
        return $this->sets[$key]['main response'];
    }

    private function getContract(): Contract
    {
        $contract = new Contract();
        $contract->contractCode = '123456';
        $contract->userName = 'User123456';
        $contract->password = 'mysecret';
        $contract->emailOnError = 'test@example.com';
        $contract->emailOnWarning = 'test@example.com';
        return $contract;
    }

    private function getConnector(): Connector
    {
        $connector = new Connector();
        $connector->application = 'TestWebShop 8.3.7';
        $connector->webKoppel = 'Acumulus 8.3.7';
        $connector->development = 'SIEL - Buro RaDer';
        $connector->remark = 'Library 8.3.7 - PHP 8.1.29';
        $connector->sourceUri = 'https://github.com/SIELOnline/libAcumulus';
        return $connector;
    }

    public function getBasicSubmit(): BasicSubmit
    {
        $submit = new BasicSubmit();
        $submit->format = $this->options[Fld::Format] ?? 'json';
        $submit->testMode = $this->options[Fld::TestMode] ?? '0';
        $submit->lang = $this->options[Fld::Lang] ?? 'nl';
        $submit->setContract($this->getContract());
        $submit->setConnector($this->getConnector());
        return $submit;
    }
}
