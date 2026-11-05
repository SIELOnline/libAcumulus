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
        'message_form_title' => 'Bericht van Acumulus',
        'message_form_header' => 'Bericht van Acumulus',
        'message_form_message_success' => '',
        'message_form_message_error' => '',
        'later' => 'Herinner me later',
        'hide' => 'Niet meer tonen',
        'no_problem' => 'OK, geen probleem.',
        'unknown_action' => "Onbekende actie '%s'",
        'newReleaseMessage' => '<p>Je gebruikt momenteel versie %3$s van de %1$s %2$s. De nieuwste versie is %4$s. <a href="%5$s" target="_blank">Update de %2$s</a> om de nieuwste functionaliteiten te gebruiken.</p>',
    ];

    protected array $en = [
        'message_form_title' => 'Message from Acumulus',
        'message_form_header' => 'Message from Acumulus',
        'hide' => "Don't show anymore",
        'later' => 'Remind me later',
        'no_problem' => 'OK, no problem.',
        'unknown_action' => "Unknown action '%s'",
        'newReleaseMessage' => '<p>You are currently using version %3$s of this %1$s %2$s. The latest version is %4$s. <a href="%5$s" target="_blank">Update the %2$s</a> to enjoy the latest features.</p>',
    ];
}
