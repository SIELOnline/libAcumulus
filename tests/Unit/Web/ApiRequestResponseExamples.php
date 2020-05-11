<?php
namespace Siel\Acumulus\Unit\Web;

class ApiRequestResponseExamples
{

    private $sets = [
        // 0
        [
            '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<myxml>
  <contract>
    <contractcode>288252</contractcode>
    <username>APIGebruiker12345</username>
    <password>mysecret</password>
    <emailonerror>erwin@burorader.com</emailonerror>
    <emailonwarning>erwin@burorader.com</emailonwarning>
  </contract>
  <format>json</format>
  <testmode>0</testmode>
  <lang>nl</lang>
  <connector>
    <application>WooCommerce 4.0.1 (WordPress: 5.4)</application>
    <webkoppel>Acumulus 5.9.0</webkoppel>
    <development>SIEL - Buro RaDer</development>
    <remark>Library 5.10.0-alpha1 - PHP 7.1.33</remark>
    <sourceuri>https://www.siel.nl/</sourceuri>
  </connector>
</myxml>
',
            '{"accounts":{"account":[{"accountid":"70582","accountnumber":"4911764","accountdescription":"Giro","accounttypeid":"1"},{"accountid":"70583","accountnumber":{},"accountdescription":"Kas","accounttypeid":"1"},{"accountid":"147659","accountnumber":"123456789","accountdescription":{},"accounttypeid":"1"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            [["accountid" => "70582","accountnumber" => "4911764","accountdescription" => "Giro","accounttypeid" => "1"],["accountid" => "70583","accountnumber" => [],"accountdescription" => "Kas","accounttypeid" => "1"],["accountid" => "147659","accountnumber" => "123456789","accountdescription" => [],"accounttypeid" => "1"]],
        ],
        // 1
        [
            '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<myxml>
  <contract>
    <contractcode>288252</contractcode>
    <username>APIGebruiker12345</username>
    <password>mysecret</password>
    <emailonerror>erwin@burorader.com</emailonerror>
    <emailonwarning>erwin@burorader.com</emailonwarning>
  </contract>
  <format>json</format>
  <testmode>0</testmode>
  <lang>nl</lang>
  <connector>
    <application>WooCommerce 4.0.1 (WordPress: 5.4)</application>
    <webkoppel>Acumulus 5.9.0</webkoppel>
    <development>SIEL - Buro RaDer</development>
    <remark>Library 5.10.0-alpha1 - PHP 7.1.33</remark>
    <sourceuri>https://www.siel.nl/</sourceuri>
  </connector>
</myxml>
',
            '{"costcenters":{"costcenter":[{"costcenterid":"48663","costcentername":"Algemeen"},{"costcenterid":"56074","costcentername":"kostenplaats 1"},{"costcenterid":"56075","costcentername":"kostenplaats 2"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            [["costcenterid" => "48663","costcentername" => "Algemeen"],["costcenterid" => "56074","costcentername" => "kostenplaats 1"],["costcenterid" => "56075","costcentername" => "kostenplaats 2"]],
        ],
        // 2
        [
            '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<myxml>
  <format>json</format>
  <testmode>1</testmode>
  <lang>nl</lang>
  <connector>
    <application>WooCommerce 4.0.1 (WordPress: 5.4)</application>
    <webkoppel>Acumulus 5.9.0</webkoppel>
    <development>SIEL - Buro RaDer</development>
    <remark>Library 5.10.0-alpha1 - PHP 7.1.33</remark>
    <sourceuri>https://www.siel.nl/</sourceuri>
  </connector>
  <vatdate>2020-02-05</vatdate>
  <vatcountry>nl</vatcountry>
</myxml>
',
            '{"errors":{"error":{"code":"403 Forbidden","codetag":"AF1001MCS","message":"Verplichte contract sectie ontbreekt"},"count_errors":"1"},"warnings":{"count_warnings":"0"},"status":"1"}',
            [],
        ],
        // 3
        [
            '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<myxml>
  <contract>
    <contractcode>288252</contractcode>
    <username>APIGebruiker12345</username>
    <password>mysecret</password>
    <emailonerror>erwin@burorader.com</emailonerror>
    <emailonwarning>erwin@burorader.com</emailonwarning>
  </contract>
  <format>json</format>
  <testmode>1</testmode>
  <lang>nl</lang>
  <connector>
    <application>WooCommerce 4.0.1 (WordPress: 5.4)</application>
    <webkoppel>Acumulus 5.9.0</webkoppel>
    <development>SIEL - Buro RaDer</development>
    <remark>Library 5.10.0-alpha1 - PHP 7.1.33</remark>
    <sourceuri>https://www.siel.nl/</sourceuri>
  </connector>
  <vatdate>2020-02-05</vatdate>
  <vatcountry>nl</vatcountry>
</myxml>
',
            '{"vatinfo":{"vat":[{"vattype":"normal","vatrate":"21.0000"},{"vattype":"reduced","vatrate":"9.0000"}]},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            [["vattype" => "normal","vatrate" => "21.0000"],["vattype" => "reduced","vatrate" => "9.0000"]],
        ],
        // 4
        [
            '<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<myxml>
  <contract>
    <contractcode>288252</contractcode>
    <username>APIGebruiker12345</username>
    <password>mysecret</password>
    <emailonerror>erwin@burorader.com</emailonerror>
    <emailonwarning>erwin@burorader.com</emailonwarning>
  </contract>
  <format>json</format>
  <testmode>1</testmode>
  <lang>nl</lang>
  <connector>
    <application>WooCommerce 4.0.1 (WordPress: 5.4)</application>
    <webkoppel>Acumulus 5.9.0</webkoppel>
    <development>SIEL - Buro RaDer</development>
    <remark>Library 5.10.0-alpha1 - PHP 7.1.33</remark>
    <sourceuri>https://www.siel.nl/</sourceuri>
  </connector>
  <customer>
    <type>3</type>
    <contactstatus>0</contactstatus>
    <companyname1>Les Camélias</companyname1>
    <companyname2>Les Camélias</companyname2>
    <fullname>Erwin Derksen</fullname>
    <salutation>Beste Erwin</salutation>
    <address1>6 avenue de Clermont</address1>
    <postalcode>63240</postalcode>
    <city>Le Mont-Dore</city>
    <countrycode>FR</countrycode>
    <country>Frankrijk</country>
    <telephone>1234567890</telephone>
    <email>erwin@burorader.com</email>
    <overwriteifexists>1</overwriteifexists>
    <mark>::1</mark>
    <invoice>
      <concept>0</concept>
      <issuedate>2020-02-05</issuedate>
      <costcenter>48663</costcenter>
      <accountnumber>70582</accountnumber>
      <paymentstatus>2</paymentstatus>
      <paymentdate>2020-04-20</paymentdate>
      <description>Order LC202040</description>
      <template>52884</template>
      <meta-currency>EUR</meta-currency>
      <meta-currency-rate>1</meta-currency-rate>
      <meta-currency-do-convert>false</meta-currency-do-convert>
      <meta-payment-method>bacs</meta-payment-method>
      <meta-invoice-amountinc>99.16</meta-invoice-amountinc>
      <meta-invoice-vatamount>16.53</meta-invoice-vatamount>
      <meta-invoice-amount>82.63</meta-invoice-amount>
      <meta-invoice-calculated>meta-invoice-amount</meta-invoice-calculated>
      <line>
        <product>Ninja Silhouette (Naam: Erwin)</product>
        <nature>Product</nature>
        <meta-id>401</meta-id>
        <quantity>1</quantity>
        <unitprice>78.503443333333</unitprice>
        <unitpriceinc>94.204132</unitpriceinc>
        <meta-unitpriceinc-precision>0.001</meta-unitpriceinc-precision>
        <meta-recalculate-price>unitprice</meta-recalculate-price>
        <vatrate>20.0000</vatrate>
        <meta-vatrate-min>19.989776606158</meta-vatrate-min>
        <meta-vatrate-max>20.00811942888</meta-vatrate-max>
        <vatamount>15.7</vatamount>
        <meta-unitprice-precision>0.01</meta-unitprice-precision>
        <meta-vatamount-precision>0.01</meta-vatamount-precision>
        <meta-vatrate-source>completor-range</meta-vatrate-source>
        <meta-vatclass-id>digital-goods</meta-vatclass-id>
        <meta-vatrate-lookup>[20]</meta-vatrate-lookup>
        <meta-vatrate-lookup-label>["VAT"]</meta-vatrate-lookup-label>
        <meta-line-type>order-item</meta-line-type>
        <meta-vatrate-range-matches>[{"vatrate":"20.0000","vattype":6}]</meta-vatrate-range-matches>
        <meta-children-merged>1</meta-children-merged>
        <meta-recalculate-old-price>78.504132</meta-recalculate-old-price>
        <meta-did-recalculate>true</meta-did-recalculate>
        <meta-vattypes-possible>6</meta-vattypes-possible>
      </line>
      <line>
        <product>Vast Tarief</product>
        <unitprice>4.1322</unitprice>
        <quantity>1</quantity>
        <vatrate>20.0000</vatrate>
        <meta-vatrate-min>19.938056523422</meta-vatrate-min>
        <meta-vatrate-max>20.234291799787</meta-vatrate-max>
        <vatamount>0.83</vatamount>
        <meta-unitprice-precision>0.001</meta-unitprice-precision>
        <meta-vatamount-precision>0.01</meta-vatamount-precision>
        <meta-vatrate-source>completor-range</meta-vatrate-source>
        <meta-vatclass-id>digital-goods</meta-vatclass-id>
        <meta-vatrate-lookup>["20"]</meta-vatrate-lookup>
        <meta-vatrate-lookup-label>["VAT"]</meta-vatrate-lookup-label>
        <meta-vatrate-lookup-source>shipping line taxes</meta-vatrate-lookup-source>
        <meta-line-type>shipping</meta-line-type>
        <nature>Service</nature>
        <meta-vatrate-range-matches>[{"vatrate":"20.0000","vattype":6}]</meta-vatrate-range-matches>
        <unitpriceinc>4.95864</unitpriceinc>
        <meta-fields-calculated>["unitpriceinc"]</meta-fields-calculated>
        <meta-vattypes-possible>6</meta-vattypes-possible>
      </line>
      <meta-lines-amount>82.635643333333</meta-lines-amount>
      <meta-lines-amountinc>99.162772</meta-lines-amountinc>
      <meta-lines-vatamount>16.53</meta-lines-vatamount>
      <vattype>6</vattype>
      <meta-vattypes-possible-invoice>1,6</meta-vattypes-possible-invoice>
      <meta-vattypes-possible-lines-intersection>6</meta-vattypes-possible-lines-intersection>
      <meta-vattypes-possible-lines-union>6</meta-vattypes-possible-lines-union>
    </invoice>
  </customer>
</myxml>
',
            '{"invoice":{"conceptid":{}},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}',
            ["conceptid" => []],
        ],
    ];

    public function getRequest($i)
    {
        return $this->sets[$i][0];
    }

    public function getResponse($i)
    {
        return $this->sets[$i][1];
    }

    public function getResponseArray($i)
    {
        return $this->sets[$i][2];
    }
}
