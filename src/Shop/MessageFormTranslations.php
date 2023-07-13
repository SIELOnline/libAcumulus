<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "plugin message" "form".
 */
class MessageFormTranslations extends TranslationCollection
{
    protected array $nl = [
        'plugin_v8_message' => '<p>Acumulus is bijgewerkt naar versie 8. <a href="%1$s" target="_blank">Lees meer over deze nieuwe versie op het Acumulus forum</a>.
 Het is belangrijk dat je:</p>
 <ul>
 <li>De <a href="%2$s">instellingen</a> controleert (met name het veld "hoofdadres").</li>
 <li>De <a href="%3$s">veldverwijzingen</a> naloopt.</li>
 </ul>',
        'later' => 'Herinner me later',
        'hide' => 'Niet meer tonen',
        'no_problem' => 'OK, geen probleem.',
        'unknown_action' => "Onbekende actie '%s'",
    ];

    protected array $en = [
        'plugin_v8_message' => '<p>Acumulus has been updated to version 8. <a href="%1$s" target="_blank">Read more about this new version onp the Acumulus forum (in Dutch)</a>.
 It is important to check:</p>
 <ul>
 <li>Check the <a href="%2$s">settings</a> (especially the field "main address").</li>
 <li>check the <a href="%3$s">field references</a>.</li>
 </ul>',
        'hide' => "Don't show anymore",
        'later' => 'Remind me later',
        'no_problem' => 'OK, no problem.',
        'unknown_action' => "Unknown action '%s'",
    ];
}
