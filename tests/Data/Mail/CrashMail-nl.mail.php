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
        'subject' => 'Je Acumulus module heeft een technisch probleem',
        'bodyText' => [
            'De Acumulus module in jouw webshop is tegen een technisch probleem
aangelopen. Dit kan een tijdelijk probleem zijn omdat b.v. de Acumulus
server even niet bereikbaar is. Als het probleem blijft aanhouden,
stuur deze mail dan door naar Acumulus support. Stuur in dat geval de
hele tekst mee, want deze is nodig om het probleem goed te kunnen
onderzoeken.

Over uw webwinkel:

Webwinkel: TestDoubles',
            'Informatie voor Acumulus support:

De informatie hieronder wordt alleen getoond om eventuele support te
vergemakkelijken, u kunt deze informatie negeren. U kunt support
contacteren door deze mail door te sturen naar
testdoubles@acumulus.nl.

Test Exception
',
        ],
        'bodyHtml' => [
            '<p>De Acumulus module in jouw webshop is tegen een technisch probleem aangelopen.
Dit kan een tijdelijk probleem zijn omdat b.v. de Acumulus server even niet bereikbaar is.
Als het probleem blijft aanhouden, stuur deze mail dan door naar Acumulus support.
Stuur in dat geval de hele tekst mee, want deze is nodig om het probleem goed te kunnen onderzoeken.</p>
<h3>Over uw webwinkel</h3>
<table style="text-align: left;">
<tr><th>Webwinkel</th><td>TestDoubles',
            '</table>
<h3>Informatie voor Acumulus support</h3>
<p>De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken, u kunt deze informatie negeren.
U kunt support contacteren door deze mail door te sturen naar testdoubles@acumulus.nl.</p>
<details><summary><span>(klik om te tonen of te verbergen)</span></summary><p>Test Exception</p>
</details>
',
        ],
    ];
