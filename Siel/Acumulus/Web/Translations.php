<?php
namespace Siel\Acumulus\Web;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Web namespace.
 */
class Translations extends TranslationCollection {

  protected $nl = array(
    'message_error'          => 'Fout',
    'message_warning'        => 'Waarschuwing',
    'message_info_for_user'  => 'De informatie hieronder wordt alleen getoond om eventuele support te vergemakkelijken. U kunt deze informatie negeren.',
    'message_sent'           => 'Verzonden bericht',
    'message_received'       => 'Ontvangen bericht',
    'message_response_0'     => 'Succes. Zonder waarschuwingen',
    'message_response_1'     => 'Mislukt. Fouten gevonden',
    'message_response_2'     => 'Succes. Met waarschuwingen',
    'message_response_3'     => 'Fout. Neem contact op met Acumulus',
    'message_response_x'     => 'Onbekende status code',
  );

  protected $en = array(
    'message_error'          => 'Error',
    'message_warning'        => 'Warning',
    'message_info_for_user'  => 'The information below is only shown to facilitate support. You may ignore these messages.',
    'message_sent'           => 'Message sent',
    'message_received'       => 'Message received',
    'message_response_0'     => 'Success. Without warnings',
    'message_response_1'     => 'Failed. Errors found',
    'message_response_2'     => 'Success. With any warnings',
    'message_response_3'     => 'Exception. Please contact Acumulus technical support',
    'message_response_x'     => 'Unknown status code',
  );

}
