<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Shop namespaceL
 * - InvoiceManager
 */
class Translations extends TranslationCollection {

  protected $nl = array(
    // Batch log messages.
    'message_batch_send_1_success' => 'Factuur voor %1$s %2$s succesvol verzonden.',
    'message_batch_send_1_errors' => 'Fout bij het versturen van de factuur voor %1$s %2$s.',
    'message_batch_send_1_warnings' => 'Waarschuwingen bij het versturen van de factuur voor %1$s %2$s.',
    'message_batch_send_1_skipped' => 'Factuur voor %1$s %2$s overgeslagen omdat deze al verstuurd is.',

  );

  protected $en = array(
    'message_batch_send_1_success' => 'Successfully sent invoice for %1$s %2$s.',
    'message_batch_send_1_errors' => 'Error while sending invoice for %1$s %2$s.',
    'message_batch_send_1_warnings' => 'Warnings while sending invoice for %1$s %2$s.',
    'message_batch_send_1_skipped' => 'Skipped invoice for %1$s %2$s (already sent).',

  );

}
