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
        'subject' => 'Voorraadmutatie verzonden naar Acumulus in testmodus: succes',
        'bodyText' => 'Onderstaande voorraadmutatie is succesvol naar Acumulus verstuurd. De
voorraadmutatie is in testmodus verstuurd en is dus niet aan uw
boekhouding toegevoegd.

Over de voorraadmutatie:

Bestelling:                1
Bestelregel:               5
Product (Webwinkel):       TEST-GRI
Mutatie:                   -11
Product (Acumulus):        1833642
Voorraadniveau (Acumulus): 30.00
Verzendresultaat:          2 - "Succes"

Informatie voor Acumulus support:

De informatie hieronder wordt alleen getoond om eventuele support te
vergemakkelijken, u kunt deze informatie negeren. U kunt support
contacteren door deze mail door te sturen naar
testdoubles@acumulus.nl.

• Request: uri=stock-transaction
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
    "productid": 1833642,
    "stockamount": -2.0,
    "stockDescription": "Bestelling 123"
}
• Response: status=200
body={
    "stock": {
        "stockamount": "30.00",
        "productid": "1833642"
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
        'bodyHtml' => '<p>Onderstaande voorraadmutatie is succesvol naar Acumulus verstuurd.
De voorraadmutatie is in testmodus verstuurd en is dus niet aan uw boekhouding toegevoegd.</p>
<h3>Over de voorraadmutatie</h3>
<table style="text-align: left;">
<tr><th>Bestelling</th><td>1</td></tr>
<tr><th>Bestelregel</th><td>5</td></tr>
<tr><th>Product (Webwinkel)</th><td><a href="#">TEST-GRI</a></td></tr>
<tr><th>Mutatie</th><td>-11</td></tr>
<tr><th>Product (Acumulus)</th><td>1833642</td></tr>
<tr><th>Voorraadniveau (Acumulus)</th><td>30.00</td></tr>
<tr><th>Verzendresultaat</th><td>2 - "Succes"</td></tr>
</table>
<h3>Informatie voor Acumulus support</h3>
<p>De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken, u kunt deze informatie negeren.
U kunt support contacteren door deze mail door te sturen naar testdoubles@acumulus.nl.</p>
<details><summary><span>(klik om te tonen of te verbergen)</span></summary><ul>
<li><span>Request: uri=stock-transaction<br>
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
    "productid": 1833642,<br>
    "stockamount": -2.0,<br>
    "stockDescription": "Bestelling 123"<br>
}</span></li>
<li><span>Response: status=200<br>
body={<br>
    "stock": {<br>
        "stockamount": "30.00",<br>
        "productid": "1833642"<br>
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
