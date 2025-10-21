<?php
/**
 * @noinspection GrazieInspection
 * @noinspection SpellCheckingInspection
 */

declare(strict_types=1);

$mail =
    [
        'from' => 'unit.test@example.com',
        'fromName' => 'Unit Test | Example',
        'to' => 'admin@example.com',
        'subject' => 'Factuur verzonden naar Acumulus in testmodus: succes, geen pdf verstuurd',
        'bodyText' => 'Onderstaande factuur is succesvol naar Acumulus verstuurd. De factuur
is in testmodus verstuurd en is dus niet aan uw boekhouding
toegevoegd.

Over de factuur:

(Webwinkel) Bestelling: 3
Acumulus factuur:       20240016
Verzendresultaat:       2 - "Succes"

Informatie voor Acumulus support:

De informatie hieronder wordt alleen getoond om eventuele support te
vergemakkelijken, u kunt deze informatie negeren. U kunt support
contacteren door deze mail door te sturen naar
testdoubles@acumulus.nl.

• Request: uri=invoice-add
submit={
    "contract": {
        "contractcode": "123456",
        "username": "User123456",
        "password": "REMOVED FOR SECURITY",
        "emailonerror": "test@example.com",
        "emailonwarning": "test@example.com"
    },
    "format": "json",
    "testmode": 1,
    "lang": "nl",
    "connector": {
        "application": "TestWebShop 8.3.7",
        "webkoppel": "Acumulus 8.3.7",
        "development": "SIEL - Buro RaDer",
        "remark": "Library 8.3.7 - PHP 8.1.29",
        "sourceuri": "https://github.com/SIELOnline/libAcumulus"
    },
    "customer": {
        "type": "3",
        "contactstatus": "0",
        "companyname1": "Les Camélias",
        "companyname2": "Les Camélias",
        "fullname": "Erwin Derksen",
        "salutation": "Beste Erwin",
        "address1": "6 avenue de Clermont",
        "postalcode": "63240",
        "city": "Le Mont-Dore",
        "countrycode": "FR",
        "country": "Frankrijk",
        "telephone": "1234567890",
        "email": "test@bexample.com",
        "overwriteifexists": "1",
        "mark": "::1",
        "invoice": {
            "concept": "0",
            "issuedate": "2020-02-05",
            "costcenter": "48663",
            "accountnumber": "70582",
            "paymentstatus": "2",
            "paymentdate": "2020-04-20",
            "description": "Order LC202040",
            "template": "52884",
            "meta-currency": "EUR",
            "meta-currency-rate": "1",
            "meta-currency-do-convert": "false",
            "meta-payment-method": "bacs",
            "meta-invoice-amountinc": "99.16",
            "meta-invoice-vatamount": "16.53",
            "meta-invoice-amount": "82.63",
            "meta-invoice-calculated": "meta-invoice-amount",
            "line": [
                {
                    "product": "Ninja Silhouette (Naam: Erwin)",
                    "nature": "Product",
                    "meta-id": "401",
                    "quantity": "1",
                    "unitprice": "78.503443333333",
                    "unitpriceinc": "94.204132",
                    "meta-unitpriceinc-precision": "0.001",
                    "meta-recalculate-price": "unitprice",
                    "vatrate": "20.0000",
                    "meta-vatrate-min": "19.989776606158",
                    "meta-vatrate-max": "20.00811942888",
                    "vatamount": "15.7",
                    "meta-unitprice-precision": "0.01",
                    "meta-vatamount-precision": "0.01",
                    "meta-vatrate-source": "completor-range",
                    "meta-vatclass-id": "digital-goods",
                    "meta-vatrate-lookup": "[20]",
                    "meta-vatrate-lookup-label": "[\\"VAT\\"]",
                    "meta-sub-type": "order-item",
                    "meta-vatrate-range-matches": "[{\\"vatrate\\":\\"20.0000\\",\\"vattype\\":6}]",
                    "meta-children-merged": "1",
                    "meta-recalculate-old-price": "78.504132",
                    "meta-did-recalculate": "true",
                    "meta-vattypes-possible": "6"
                },
                {
                    "product": "Vast Tarief",
                    "unitprice": "4.1322",
                    "quantity": "1",
                    "vatrate": "20.0000",
                    "meta-vatrate-min": "19.938056523422",
                    "meta-vatrate-max": "20.234291799787",
                    "vatamount": "0.83",
                    "meta-unitprice-precision": "0.001",
                    "meta-vatamount-precision": "0.01",
                    "meta-vatrate-source": "completor-range",
                    "meta-vatclass-id": "digital-goods",
                    "meta-vatrate-lookup": "[\\"20\\"]",
                    "meta-vatrate-lookup-label": "[\\"VAT\\"]",
                    "meta-vatrate-lookup-source": "shipping line taxes",
                    "meta-sub-type": "shipping",
                    "nature": "Service",
                    "meta-vatrate-range-matches": "[{\\"vatrate\\":\\"20.0000\\",\\"vattype\\":6}]",
                    "unitpriceinc": "4.95864",
                    "meta-fields-calculated": "[\\"unitPriceInc\\"]",
                    "meta-vattypes-possible": "6"
                }
            ],
            "meta-lines-amount": "82.635643333333",
            "meta-lines-amountinc": "99.162772",
            "meta-lines-vatamount": "16.53",
            "vattype": "6",
            "meta-vattypes-possible-invoice": "1,6",
            "meta-vattypes-possible-lines-intersection": "6",
            "meta-vattypes-possible-lines-union": "6"
        }
    }
}
• Response: status=200
body={
    "invoice": {
        "invoicenumber": "20240016",
        "token": "lc9gfgYN8bQQHnIV99r4jmDraKhQoeIj",
        "entryid": "55393014",
        "contactid": "9326320",
        "conceptid": []
    },
    "errors": {
        "count_errors": "0"
    },
    "warnings": {
        "count_warnings": "0"
    },
    "status": "0"
}
',
        'bodyHtml' => '<p>Onderstaande factuur is succesvol naar Acumulus verstuurd.
De factuur is in testmodus verstuurd en is dus niet aan uw boekhouding toegevoegd.</p>
<h3>Over de factuur</h3>
<table style="text-align: left;">
<tr><th>(Webwinkel) Bestelling</th><td>3</td></tr>
<tr><th>Acumulus factuur</th><td>20240016</td></tr>
<tr><th>Verzendresultaat</th><td>2 - "Succes"</td></tr>
</table>
<h3>Informatie voor Acumulus support</h3>
<p>De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken, u kunt deze informatie negeren.
U kunt support contacteren door deze mail door te sturen naar testdoubles@acumulus.nl.</p>
<details><summary><span>(klik om te tonen of te verbergen)</span></summary><ul>
<li><span>Request: uri=invoice-add<br>
submit={<br>
    "contract": {<br>
        "contractcode": "123456",<br>
        "username": "User123456",<br>
        "password": "REMOVED FOR SECURITY",<br>
        "emailonerror": "test@example.com",<br>
        "emailonwarning": "test@example.com"<br>
    },<br>
    "format": "json",<br>
    "testmode": 1,<br>
    "lang": "nl",<br>
    "connector": {<br>
        "application": "TestWebShop 8.3.7",<br>
        "webkoppel": "Acumulus 8.3.7",<br>
        "development": "SIEL - Buro RaDer",<br>
        "remark": "Library 8.3.7 - PHP 8.1.29",<br>
        "sourceuri": "https://github.com/SIELOnline/libAcumulus"<br>
    },<br>
    "customer": {<br>
        "type": "3",<br>
        "contactstatus": "0",<br>
        "companyname1": "Les Camélias",<br>
        "companyname2": "Les Camélias",<br>
        "fullname": "Erwin Derksen",<br>
        "salutation": "Beste Erwin",<br>
        "address1": "6 avenue de Clermont",<br>
        "postalcode": "63240",<br>
        "city": "Le Mont-Dore",<br>
        "countrycode": "FR",<br>
        "country": "Frankrijk",<br>
        "telephone": "1234567890",<br>
        "email": "test@bexample.com",<br>
        "overwriteifexists": "1",<br>
        "mark": "::1",<br>
        "invoice": {<br>
            "concept": "0",<br>
            "issuedate": "2020-02-05",<br>
            "costcenter": "48663",<br>
            "accountnumber": "70582",<br>
            "paymentstatus": "2",<br>
            "paymentdate": "2020-04-20",<br>
            "description": "Order LC202040",<br>
            "template": "52884",<br>
            "meta-currency": "EUR",<br>
            "meta-currency-rate": "1",<br>
            "meta-currency-do-convert": "false",<br>
            "meta-payment-method": "bacs",<br>
            "meta-invoice-amountinc": "99.16",<br>
            "meta-invoice-vatamount": "16.53",<br>
            "meta-invoice-amount": "82.63",<br>
            "meta-invoice-calculated": "meta-invoice-amount",<br>
            "line": [<br>
                {<br>
                    "product": "Ninja Silhouette (Naam: Erwin)",<br>
                    "nature": "Product",<br>
                    "meta-id": "401",<br>
                    "quantity": "1",<br>
                    "unitprice": "78.503443333333",<br>
                    "unitpriceinc": "94.204132",<br>
                    "meta-unitpriceinc-precision": "0.001",<br>
                    "meta-recalculate-price": "unitprice",<br>
                    "vatrate": "20.0000",<br>
                    "meta-vatrate-min": "19.989776606158",<br>
                    "meta-vatrate-max": "20.00811942888",<br>
                    "vatamount": "15.7",<br>
                    "meta-unitprice-precision": "0.01",<br>
                    "meta-vatamount-precision": "0.01",<br>
                    "meta-vatrate-source": "completor-range",<br>
                    "meta-vatclass-id": "digital-goods",<br>
                    "meta-vatrate-lookup": "[20]",<br>
                    "meta-vatrate-lookup-label": "[\\"VAT\\"]",<br>
                    "meta-sub-type": "order-item",<br>
                    "meta-vatrate-range-matches": "[{\\"vatrate\\":\\"20.0000\\",\\"vattype\\":6}]",<br>
                    "meta-children-merged": "1",<br>
                    "meta-recalculate-old-price": "78.504132",<br>
                    "meta-did-recalculate": "true",<br>
                    "meta-vattypes-possible": "6"<br>
                },<br>
                {<br>
                    "product": "Vast Tarief",<br>
                    "unitprice": "4.1322",<br>
                    "quantity": "1",<br>
                    "vatrate": "20.0000",<br>
                    "meta-vatrate-min": "19.938056523422",<br>
                    "meta-vatrate-max": "20.234291799787",<br>
                    "vatamount": "0.83",<br>
                    "meta-unitprice-precision": "0.001",<br>
                    "meta-vatamount-precision": "0.01",<br>
                    "meta-vatrate-source": "completor-range",<br>
                    "meta-vatclass-id": "digital-goods",<br>
                    "meta-vatrate-lookup": "[\\"20\\"]",<br>
                    "meta-vatrate-lookup-label": "[\\"VAT\\"]",<br>
                    "meta-vatrate-lookup-source": "shipping line taxes",<br>
                    "meta-sub-type": "shipping",<br>
                    "nature": "Service",<br>
                    "meta-vatrate-range-matches": "[{\\"vatrate\\":\\"20.0000\\",\\"vattype\\":6}]",<br>
                    "unitpriceinc": "4.95864",<br>
                    "meta-fields-calculated": "[\\"unitPriceInc\\"]",<br>
                    "meta-vattypes-possible": "6"<br>
                }<br>
            ],<br>
            "meta-lines-amount": "82.635643333333",<br>
            "meta-lines-amountinc": "99.162772",<br>
            "meta-lines-vatamount": "16.53",<br>
            "vattype": "6",<br>
            "meta-vattypes-possible-invoice": "1,6",<br>
            "meta-vattypes-possible-lines-intersection": "6",<br>
            "meta-vattypes-possible-lines-union": "6"<br>
        }<br>
    }<br>
}</span></li>
<li><span>Response: status=200<br>
body={<br>
    "invoice": {<br>
        "invoicenumber": "20240016",<br>
        "token": "lc9gfgYN8bQQHnIV99r4jmDraKhQoeIj",<br>
        "entryid": "55393014",<br>
        "contactid": "9326320",<br>
        "conceptid": []<br>
    },<br>
    "errors": {<br>
        "count_errors": "0"<br>
    },<br>
    "warnings": {<br>
        "count_warnings": "0"<br>
    },<br>
    "status": "0"<br>
}</span></li></ul>
</details>
',
    ];
