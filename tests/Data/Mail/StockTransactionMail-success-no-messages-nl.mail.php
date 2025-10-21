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
        'subject' => 'Voorraadmutatie verzonden naar Acumulus: succes',
        'bodyText' => 'Onderstaande voorraadmutatie is succesvol naar Acumulus verstuurd.

Over de voorraadmutatie:

Bestelling:                1
Bestelregel:               5
Product (Webwinkel):       TEST-GRI
Mutatie:                   -5
Product (Acumulus):        1833642
Voorraadniveau (Acumulus): 30.00
Verzendresultaat:          2 - "Succes"
',
        'bodyHtml' => '<p>Onderstaande voorraadmutatie is succesvol naar Acumulus verstuurd.</p>
<h3>Over de voorraadmutatie</h3>
<table style="text-align: left;">
<tr><th>Bestelling</th><td>1</td></tr>
<tr><th>Bestelregel</th><td>5</td></tr>
<tr><th>Product (Webwinkel)</th><td><a href="#">TEST-GRI</a></td></tr>
<tr><th>Mutatie</th><td>-5</td></tr>
<tr><th>Product (Acumulus)</th><td>1833642</td></tr>
<tr><th>Voorraadniveau (Acumulus)</th><td>30.00</td></tr>
<tr><th>Verzendresultaat</th><td>2 - "Succes"</td></tr>
</table>
',
    ];
