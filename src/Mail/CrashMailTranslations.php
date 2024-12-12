<?php

declare(strict_types=1);

namespace Siel\Acumulus\Mail;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for {@see \Siel\Acumulus\Mail\CrashMail}.
 */
class CrashMailTranslations extends TranslationCollection
{
    protected array $nl = [
        // Subject base.
        'mail_subject' => 'Je {module_name} {module} heeft een technisch probleem',

        // Body main.
        'mail_body_intro_crash' => 'De {module_name} {module} in jouw webshop is tegen een technisch probleem aangelopen.',
        'mail_body_intro_temporary' => 'Dit kan een tijdelijk probleem zijn omdat b.v. de Acumulus server even niet bereikbaar is.',
        'mail_body_intro_contact_support' => 'Als het probleem blijft aanhouden, stuur deze mail dan door naar Acumulus support.',
        'mail_body_intro_forward_all' => 'Stuur in dat geval de hele tekst mee, want deze is nodig om het probleem goed te kunnen onderzoeken.',

        // Body about.
        'mail_about_header' => 'Over uw webwinkel',
    ];

    protected array $en = [
        // Subject base.
        'mail_subject' => 'Your {module_name} {module} is experiencing a technical issue',

        // Body main.
        'mail_body_intro_crash' => 'The {module_name} {module} in your web shop is experienced a technical issue.',
        'mail_body_intro_temporary' =>  'This can be a temporary problem e.g. because the Acumulus server cannot be reached.',
        'mail_body_intro_contact_support' => 'If the problem remains, please forward this mail to Acumulus support.',
        'mail_body_intro_forward_all' => 'If you forward this mail, please let the information below intact because we need it to research the problem.',

        // Body about.
        'mail_about_header' => 'About your webshop',
    ];
}
