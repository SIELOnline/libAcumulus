<?php

namespace Siel\Acumulus\Unit\ApiClient;

use Siel\Acumulus\Tag;

class ApiRequestResponseExamples
{

    private $sets = [
        'accounts' => [
            'submit' => [
                'contract' => [
                    'contractcode' => '288252',
                    'username' => 'APIGebruiker12345',
                    'password' => 'mysecret',
                    'emailonerror' => 'erwin@burorader.com',
                    'emailonwarning' => 'erwin@burorader.com',
                ],
                'format' => 'json',
                'testmode' => '0',
                'lang' => 'nl',
                'connector' => [
                    'application' => 'WooCommerce 4.0.1 (WordPress: 5.4)',
                    'webkoppel' => 'Acumulus 5.9.0',
                    'development' => 'SIEL - Buro RaDer',
                    'remark' => 'Library 5.10.0-alpha1 - PHP 7.1.33',
                    'sourceuri' => 'https://www.siel.nl/',
                ],
            ],
            'response body' => '{"accounts":{"account":[{"accountid":"70582","accountnumber":"4911764","accountdescription":"Giro","accounttypeid":"1"},{"accountid":"70583","accountnumber":{},"accountdescription":"Kas","accounttypeid":"1"},{"accountid":"147659","accountnumber":"123456789","accountdescription":{},"accounttypeid":"1"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'main response' => [
                ['accountid' => '70582', 'accountnumber' => '4911764', 'accountdescription' => 'Giro', 'accounttypeid' => '1'],
                ['accountid' => '70583', 'accountnumber' => [], 'accountdescription' => 'Kas', 'accounttypeid' => '1'],
                ['accountid' => '147659', 'accountnumber' => '123456789', 'accountdescription' => [], 'accounttypeid' => '1'],
            ],
        ],

        'costcenters' => [
            'submit' => [
                'contract' => [
                    'contractcode' => '288252',
                    'username' => 'APIGebruiker12345',
                    'password' => 'mysecret',
                    'emailonerror' => 'erwin@burorader.com',
                    'emailonwarning' => 'erwin@burorader.com',
                ],
                'format' => 'json',
                'testmode' => '0',
                'lang' => 'nl',
                'connector' => [
                    'application' => 'WooCommerce 4.0.1 (WordPress: 5.4)',
                    'webkoppel' => 'Acumulus 5.9.0',
                    'development' => 'SIEL - Buro RaDer',
                    'remark' => 'Library 5.10.0-alpha1 - PHP 7.1.33',
                    'sourceuri' => 'https://www.siel.nl/',
                ],
            ],
            'resonse body' => '{"costcenters":{"costcenter":[{"costcenterid":"48663","costcentername":"Algemeen"},{"costcenterid":"56074","costcentername":"kostenplaats 1"},{"costcenterid":"56075","costcentername":"kostenplaats 2"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'main response' => [
                ['costcenterid' => '48663', 'costcentername' => 'Algemeen'],
                ['costcenterid' => '56074', 'costcentername' => 'kostenplaats 1'],
                ['costcenterid' => '56075', 'costcentername' => 'kostenplaats 2'],
            ],
        ],

        'no_contract' => [
            'submit' => [
                'format' => 'json',
                'testmode' => '1',
                'lang' => 'nl',
                'connector' => [
                    'application' => 'WooCommerce 4.0.1 (WordPress: 5.4)',
                    'webkoppel' => 'Acumulus 5.9.0',
                    'development' => 'SIEL - Buro RaDer',
                    'remark' => 'Library 5.10.0-alpha1 - PHP 7.1.33',
                    'sourceuri' => 'https://www.siel.nl/',
                ],
                'vatdate' => '2020-02-05',
                'vatcountry' => 'nl',
            ],
            'response body' => '{"errors":{"error":{"code":"403 Forbidden","codetag":"AF1001MCS","message":"Verplichte contract sectie ontbreekt"},"count_errors":"1"},"warnings":{"count_warnings":"0"},"status":"1"}',
            'main response' => [],
        ],

        'vatinfo' => [
            'submit' => [
                'contract' => [
                    'contractcode' => '288252',
                    'username' => 'APIGebruiker12345',
                    'password' => 'mysecret',
                    'emailonerror' => 'erwin@burorader.com',
                    'emailonwarning' => 'erwin@burorader.com',
                ],
                'format' => 'json',
                'testmode' => '1',
                'lang' => 'nl',
                'connector' => [
                    'application' => 'WooCommerce 4.0.1 (WordPress: 5.4)',
                    'webkoppel' => 'Acumulus 5.9.0',
                    'development' => 'SIEL - Buro RaDer',
                    'remark' => 'Library 5.10.0-alpha1 - PHP 7.1.33',
                    'sourceuri' => 'https://www.siel.nl/',
                ],
                'vatdate' => '2020-02-05',
                'vatcountry' => 'nl',
            ],
            'response body' => '{"vatinfo":{"vat":[{"vattype":"normal","vatrate":"21.0000"},{"vattype":"reduced","vatrate":"9.0000"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'main response' => [['vattype' => 'normal', 'vatrate' => '21.0000'], ['vattype' => 'reduced', 'vatrate' => '9.0000']],
        ],

        'vatinfo-empty-return' => [
            'submit' => [
                'contract' => [
                    'contractcode' => '288252',
                    'username' => 'APIGebruiker12345',
                    'password' => 'mysecret',
                    'emailonerror' => 'erwin@burorader.com',
                    'emailonwarning' => 'erwin@burorader.com',
                ],
                'format' => 'json',
                'testmode' => '1',
                'lang' => 'nl',
                'connector' => [
                    'application' => 'WooCommerce 4.0.1 (WordPress: 5.4)',
                    'webkoppel' => 'Acumulus 5.9.0',
                    'development' => 'SIEL - Buro RaDer',
                    'remark' => 'Library 5.10.0-alpha1 - PHP 7.1.33',
                    'sourceuri' => 'https://www.siel.nl/',
                ],
                'vatdate' => '2014-01-01',
                'vatcountry' => 'fr',
            ],
            'response body' => '{"vatinfo":{},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'main response' => [],
        ],

        'invoice-add' => [
            'submit' => [
                'contract' => [
                    'contractcode' => '288252',
                    'username' => 'APIGebruiker12345',
                    'password' => 'mysecret',
                    'emailonerror' => 'erwin@burorader.com',
                    'emailonwarning' => 'erwin@burorader.com',
                ],
                'format' => 'json',
                'testmode' => '1',
                'lang' => 'nl',
                'connector' => [
                    'application' => 'WooCommerce 4.0.1 (WordPress: 5.4)',
                    'webkoppel' => 'Acumulus 5.9.0',
                    'development' => 'SIEL - Buro RaDer',
                    'remark' => 'Library 5.10.0-alpha1 - PHP 7.1.33',
                    'sourceuri' => 'https://www.siel.nl/',
                ],
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
                    'email' => 'erwin@burorader.com',
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
                                'meta-line-type' => 'order-item',
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
                                'meta-line-type' => 'shipping',
                                'nature' => 'Service',
                                'meta-vatrate-range-matches' => '[{"vatrate":"20.0000","vattype":6}]',
                                'unitpriceinc' => '4.95864',
                                'meta-fields-calculated' => '["unitpriceinc"]',
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
            'response body' => '{"invoice":{"conceptid":{}},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            'main response' => ['conceptid' => []],
        ],

        'signup' => [
            'submit' => [
                Tag::CompanyTypeId => 1,
                Tag::CompanyName => 'My Company',
                Tag::FullName => 'Smith',
                Tag::LoginName => 'John',
                Tag::Gender => 'M',
                Tag::Address => 'Straat 5',
                Tag::PostalCode => '1234 AB',
                Tag::City => 'Amsterdam',
                Tag::Email => 'john.doe@example.com',
            ],
            'response body' => '{"signup:":{"contractcode":"123456","contractloginname":"myuser","contractpassword":"mysecret","contractstartdate":"2022-02-22","contractenddate":"","contractapiuserloginname":"myapiuser","contractapiuserpassword":"mysecret"},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
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
    ];

    public function getSubmit($key)
    {
        return $this->sets[$key]['submit'];
    }

    public function getResponseBody($key)
    {
        return $this->sets[$key]['response body'];
    }

    public function getMainResponse($key)
    {
        return $this->sets[$key]['main response'];
    }
}
