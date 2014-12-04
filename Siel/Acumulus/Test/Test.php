<?php
/**
 * @file Contains Siel\Acumulus\Test\Test.
 */

namespace Siel\Acumulus\Test;

use Siel\Acumulus\Common\WebAPI;

/**
 * Test defines a Class for test purposes.
 */
class Test {
  /** @var WebAPI */
  protected $webApi;

  public function __construct() {
  }

  public function run() {

    $this->webApi = new WebAPI(new TestConfig('nl'));
    $results = '';

    $results .= "Test getPicklistAccounts:\n";
    $results .= var_export($this->testPicklistAccounts(), true);
    $results .= "\n";
    $results .= "Test getPicklistContactTypes:\n";
    $results .= var_export($this->testPicklistContactTypes(), true);
    $results .= "\n";
    $results .= "Test getPicklistCostCenters():\n";
    $results .= var_export($this->testPicklistCostCenters(), true);
    $results .= "\n";
    $results .= "Test getPicklistCostTypes():\n";
    $results .= var_export($this->testPicklistCostTypes(), true);
    $results .= "\n";
    $results .= "Test getPicklistInvoiceTemplates():\n";
    $results .= var_export($this->testPicklistInvoiceTemplates(), true);
    $results .= "\n";
    $results .= "Test getPicklistVATTypes():\n";
    $results .= var_export($this->testPicklistVatTypes(), true);
    $results .= "\n";
    $results .= "Test getLookupVatInfo('nl'):\n";
    $results .= var_export($this->testLookupVatInfo('nl', '2014-11-20'), true);
    $results .= "\n";
    $results .= "Test getLookupVatInfo('ie'):\n";
    $results .= var_export($this->testLookupVatInfo('ie', '2015-01-01'), true);
    $results .= "\n";

    $results .= "Test addInvoice():\n";
    $results .= var_export($this->testAddInvoice(), true);
    $results .= "\n";

    return $results;
  }

  public function testAddInvoice() {
    $customer = array(
      //'type', // empty = default customer type
      'companyname1' => 'Mijn eerste klant',
      //'companyname2',
      'fullname' => 'Jan Doedel',
      'salutation' => 'dhr',
      'address1' => 'Stationstraat 35',
      //'address2',
      'postalcode' => '1234 AB',
      'city' => 'Stad',
      'countrycode' => 'nl',
      //'vatnumber',
      'telephone' => '030 1234567',
      //'fax',
      'email' => 'erwin@burorader.com',
      'overwriteifexists' => 1, // 0 = no , 1 = yes
      'bankaccountnumber' => '4911764',
      //'mark' => '',
    );
    $invoice = array(
      'concept' => 0, // 0 = no concept (default), 1 = concept
      //'number', // empty = get from Acumulus
      'vattype' => 1, // 1 = national (gewone factuur) (default), 2 =national reverse charge (verlegde BTW), 3 = international reverse charge (intracommunautaire levering), 4 = export outside EU (export), 5 = margin scheme (marge regeling)
      //'issuedate', // empty = current date
      //'costcenter', // empty = send default cost center
      //'accountnumber', // empty = send default account number
      'paymentstatus' => 1, // 1 = due (default), 2 = paid
      //'paymentdate', // empty = today
      'description' => 'test factuur',
      //'template', // empty = send default invoice template
    );
    $line1 = array(
      'itemnumber' => 'ART1',
      'product' => 'Rolletje plakband',
      'unitprice' => 0.05,
      'vatrate' => 21,
      'quantity' => 10,
      //'costprice',
    );
    $line2 = array(
      'itemnumber' => 'ART2',
      'product' => 'Test2 & =<>#$%^`~"\';:? co',
      'unitprice' => 1.25,
      'vatrate' => 21,
      'quantity' => 5,
      //'costprice',
    );
    $invoice['line'] = array(
      $line1,
      $line2,
    );
    $customer['invoice'] = $invoice;
    $message['customer'] = $customer;
    return $this->webApi->invoiceAdd($message);
  }

  public function testPicklistAccounts() {
    return $this->webApi->getPicklistAccounts();
  }

  public function testPicklistContactTypes() {
    return $this->webApi->getPicklistContactTypes();
  }

  public function testPicklistCostCenters() {
    return $this->webApi->getPicklistCostCenters();
  }

  public function testPicklistCostTypes() {
    return $this->webApi->getPicklistCostHeadings();
  }

  public function testPicklistInvoiceTemplates() {
    return $this->webApi->getPicklistInvoiceTemplates();
  }

  public function testPicklistVatTypes() {
    return $this->webApi->getPicklistVatTypes();
  }

  public function testLookupVatInfo($countryCode, $date = '') {
    return $this->webApi->getVatInfo($countryCode, $date);
  }
}
