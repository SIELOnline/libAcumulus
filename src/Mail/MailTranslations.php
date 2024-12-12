<?php

declare(strict_types=1);

namespace Siel\Acumulus\Mail;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for {@see \Siel\Acumulus\Mail\Mail}.
 */
class MailTranslations extends TranslationCollection
{
    protected array $nl = [
        // Subject base: to be overridden.
        'mail_subject' => 'Mail van uw webwinkel',
        'mail_subject_test_mode' => ' in testmodus',

        // Subject result.
        'mail_subject_null' => '',
        'mail_subject_success' => 'succes',
        'mail_subject_warning' => 'waarschuwing(en)',
        'mail_subject_error' => 'fout(en)',
        'mail_subject_exception' => 'ernstige fout',
        'message_not_created' => 'niet aangemaakt in {module_name}',

        // From name.
        'mail_sender_name' => 'Uw webwinkel',

        // Body main: to be overridden.
        'mail_body_main_paragraph' => '',

        // Body about: to be overridden.
        'mail_about_header' => 'Over',

        // Body messages: can be overridden.
        'send_status' => 'Verzendresultaat',
        'mail_messages_header' => 'Meldingen',
        'mail_messages_desc_text' => 'Meer informatie over de terugkoppeling van de vermeldde foutcodes kunt u vinden op https://www.siel.nl/acumulus/API/Basic_Response/',
        'mail_messages_desc_html' => '<p>Meer informatie over de terugkoppeling van vermeldde foutcodes kunt u vinden op <a href="https://www.siel.nl/acumulus/API/Basic_Response/">{module_name} - Basic response</a>.</p>',

        // Body support: can be overridden.
        'mail_support_header' => 'Informatie voor {module_name} support',
        'mail_support_desc' => 'De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken, u kunt deze informatie negeren.',
        'mail_support_contact' => 'U kunt support contacteren door deze mail door te sturen naar {support_mail}.',
    ];

    protected array $en = [
        // Subject base: to be overridden.
        'mail_subject' => 'Mail from your webshop',
        'mail_subject_test_mode' => ' in test mode',

        // Subject result.
        'mail_subject_null' => '',
        'mail_subject_success' => 'success',
        'mail_subject_warning' => 'warning(s)',
        'mail_subject_error' => 'error(s)',
        'mail_subject_exception' => 'serious error',
        'message_not_created' => 'not created in {module_name}',

        // From name.
        'mail_sender_name' => 'Your web store',

        // Body main: to be overridden.
        'mail_body_main_paragraph' => '',

        // Body about: to be overridden.
        'mail_about_header' => 'About',

        // Body messages: can be overridden.
        'send_status' => 'Verzendresultaat',
        'mail_messages_header' => 'Messages:',
        'mail_messages_desc_text' => 'At https://www.siel.nl/acumulus/API/Basic_Response/ you can find more information regarding error codes, warnings and responses.',
        'mail_messages_desc_html' => '<p>At <a href="https://www.siel.nl/acumulus/API/Basic_Response/">{module_name} - Basic responses</a> you can find more information regarding error codes, warnings and responses.</p>',

        // Body support: can be overridden.
        'mail_support_header' => 'Information for {module_name} support:',
        'mail_support_desc' => 'The information below is only shown to facilitate support, you may ignore it.',
        'mail_support_contact' => 'You can contact support by forwarding this mail to {support_mail}.',
    ];
}
