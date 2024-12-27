<?php
/**
 * @noinspection HtmlUnknownTarget
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "plugin message" "form".
 */
class MessageFormTranslations extends TranslationCollection
{
    protected array $nl = [
        // https://forum.acumulus.nl/index.php/topic,8731.msg48337.html
        'plugin_v84_message' => '<p>Acumulus is bijgewerkt naar versie 8.4. Deze versie bevat nu het doorsturen van voorraadmutaties naar Acumulus. '
            . 'U kunt dit aanzetten op het <a href="%1$s">Acumulus instellingen formulier</a>. '
            . 'Op het Acumulus forum vindt u het document <a href="%2$s" target="_blank">Voorraadbeheer in Acumulus en uw webshop</a> dat het proces toelicht.</p>',
        'later' => 'Herinner me later',
        'hide' => 'Niet meer tonen',
        'no_problem' => 'OK, geen probleem.',
        'unknown_action' => "Onbekende actie '%s'",
    ];

    protected array $en = [
        'plugin_v84_message' => '<p>Acumulus has been updated to version 8.4. This version now allows to send stock mutations to Acumulus. '
            . 'you can enable this feature on the <a href="%1$s">Acumulus settings form</a>. '
            . 'At our Acumulus forum, you can find the document <a href="%2$s" target="_blank">Stock management in Acumulus and your web shop (Dutch)</a> that explains the feature.</p>',
        'hide' => "Don't show anymore",
        'later' => 'Remind me later',
        'no_problem' => 'OK, no problem.',
        'unknown_action' => "Unknown action '%s'",
    ];
}
