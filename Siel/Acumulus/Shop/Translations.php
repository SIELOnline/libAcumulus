<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Shop namespace:
 * - InvoiceManager
 */
class Translations extends TranslationCollection {

  protected $nl = array(
    // Batch log messages.
    'message_batch_send_1_success' => 'Factuur voor %1$s %2$s is succesvol verzonden.',
    'message_batch_send_1_errors' => 'Fout bij het versturen van de factuur voor %1$s %2$s.',
    'message_batch_send_1_warnings' => 'Waarschuwingen bij het versturen van de factuur voor %1$s %2$s.',
    'message_batch_send_1_skipped' => 'Factuur voor %1$s %2$s is overgeslagen omdat deze al verstuurd is.',
    'message_batch_send_1_prevented_invoiceCreated' => 'Factuur voor %1$s %2$s is overgeslagen omdat het versturen tegengehouden is door het event \'AcumulusInvoiceCreated\'.',
    'message_batch_send_1_prevented_invoiceCompleted' => 'Factuur voor %1$s %2$s is overgeslagen omdat het versturen tegengehouden is door het event \'AcumulusInvoiceCompleted\'.',

  );

  protected $en = array(
    'message_batch_send_1_success' => 'Successfully sent invoice for %1$s %2$s.',
    'message_batch_send_1_errors' => 'Error while sending invoice for %1$s %2$s.',
    'message_batch_send_1_warnings' => 'Warnings while sending invoice for %1$s %2$s.',
    'message_batch_send_1_skipped' => 'Skipped invoice for %1$s %2$s (already sent).',
    'message_batch_send_1_prevented_invoiceCreated' => 'Skipped invoice for %1$s %2$s (sending prevented by event \'AcumulusInvoiceCreated\').',
    'message_batch_send_1_prevented_invoiceCompleted' => 'Skipped invoice for %1$s %2$s (sending prevented by event \'AcumulusInvoiceCompleted\').',

  );

}
