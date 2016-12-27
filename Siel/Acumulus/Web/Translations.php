<?php
namespace Siel\Acumulus\Web;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Web namespace.
 */
class Translations extends TranslationCollection
{
    protected $nl = array(

        'message_error' => 'Fout',
        'message_warning' => 'Waarschuwing',
        'message_info_for_user' => 'De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken. U kunt deze informatie negeren.',
        'message_sent' => 'Verzonden bericht',
        'message_received' => 'Ontvangen bericht',
        'message_response_success' => 'Succes, zonder waarschuwingen.',
        'message_response_errors' => 'Mislukt, fouten gevonden.',
        'message_response_warnings' => 'Succes, met waarschuwingen.',
        'message_response_exception' => 'Fout, neem contact op met Acumulus.',
        'message_response_unknown' => 'Onbekende status code.',
    );

    protected $en = array(
        'message_error' => 'Error',
        'message_warning' => 'Warning',
        'message_info_for_user' => 'The information below is only shown to facilitate support. You may ignore these messages.',
        'message_sent' => 'Message sent',
        'message_received' => 'Message received',
        'message_response_success' => 'Success, without warnings.',
        'message_response_errors' => 'Failed, errors found.',
        'message_response_warnings' => 'Success, with warnings.',
        'message_response_exception' => 'Exception, please contact Acumulus technical support.',
        'message_response_unknown' => 'Unknown status code.',
    );
}
