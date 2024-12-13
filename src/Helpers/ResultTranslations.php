<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Contains translations for invoice send result logging.
 *
 * @noinspection PhpUnused : Loaded by the Container.
 */
class ResultTranslations extends TranslationCollection
{
    protected array $nl = [
        /** {@see \Siel\Acumulus\Helpers\Result::getLogText()} */
        'message_reason' => '%1$s (reden: %2$s)',
        /** {@see \Siel\Acumulus\Invoice\InvoiceManager::getSendResultLogText()} */
        'message_invoice_send' => '%1$s: %2$s is %3$s',
        'message_invoice_source' => 'Factuur voor %1$s %2$s',
        /** {@see \Siel\Acumulus\Invoice\InvoiceManager::getSendResultLogText()} */
        'message_stock_transaction_send' => '%1$s(%2$s, %3$s): %4$s %5$s',
        'message_stock_transaction_source' => "Voorraadupdate voor product '%1\$s': %2\$+f",
        /** {@see \Siel\Acumulus\Helpers\Result::getActionText()} */
        'action_sent' => 'is verzonden',
        'action_not_sent' => 'is niet verzonden',
        'action_unknown' => 'nog niet bekend',
        /** {@see \Siel\Acumulus\Helpers\Result::getStatusMessages()} */
        'reason_not_sent_local_errors' => 'verzenden tegengehouden omdat er lokaal fouten zijn geconstateerd',
        'reason_not_sent_dry_run' => 'verzenden tegengehouden door optie om niet daadwerkelijk te versturen',
        'reason_unknown' => 'onbekende reden: %d',
        'reason_sent_test_mode' => 'test modus',
        /** {@see \Siel\Acumulus\Invoice\InvoiceAddResult::getStatusMessages()} */
        'reason_sent_new' => 'nieuwe verzending',
        'reason_sent_new_status_change' => 'nieuwe verzending en %1$s in [%2$s]',
        'reason_sent_forced' => 'geforceerd',
        'reason_sent_lock_expired' => 'nieuwe verzending omdat de vorige poging is mislukt',
        /** {@see \Siel\Acumulus\Invoice\InvoiceAddResult::getStatusMessages()} */
        'reason_not_sent_alreadySent' => 'is al eerder verzonden',
        'reason_not_sent_triggerCreditNoteEvent_None' => 'optie om creditfactuur automatisch te verzenden niet aangezet',
        'reason_not_sent_wrongStatus' => 'verkeerde status: %1$s niet in [%2$s]',
        'reason_not_sent_empty_invoice' => '0-bedrag factuur',
        'reason_not_sent_not_enabled_triggerInvoiceCreate' => 'optie "verzenden op aanmaken winkelfactuur" niet aangezet',
        'reason_not_sent_not_enabled_triggerInvoiceSent' => 'optie "verzenden op versturen winkelfactuur naar klant" niet aangezet',
        'reason_not_sent_alreadySending' => 'andere verzending aan de gang',
        'reason_not_sent_lockNotAcquired' => 'kon geen lock bemachtigen',
        'reason_not_sent_no_invoice_lines' => 'factuur heeft geen regels',
        'reason_not_sent_prevented_invoiceCreated' => 'verzenden tegengehouden door het event "AcumulusInvoiceCreated"',
        'reason_not_sent_prevented_invoiceCompleted' => 'verzenden tegengehouden door het event "AcumulusInvoiceSendBefore"',
        /** {@see \Siel\Acumulus\Product\StockTransactionResult::getStatusMessages()} */
        'reason_not_sent_not_enabled' => 'voorraadbeheer niet actief',
        'reason_not_sent_disabled_product' => 'Geen voorraadbeheer voor dit product',
        'reason_not_sent_no_product' => 'Product niet gevonden voor factuurregel',
        'reason_not_sent_zero_change' => 'Geen toe of afname van het voorraadniveau',
        'reason_not_sent_no_value_to_match' => 'Zoekwaarde in product is leeg',
        'reason_not_sent_no_match_in_acumulus' => 'Geen product in Acumulus voor zoekwaarde',
        'reason_not_sent_multiple_matches_in_acumulus' => 'Meerdere producten gevonden voor zoekwaarde',
        'reason_sent' => 'alles OK',
    ];

    protected array $en = [
        /** {@see \Siel\Acumulus\Helpers\Result::getLogText()} */
        'message_reason' => '%1$s (reason: %2$s)',
        /** {@see \Siel\Acumulus\Invoice\InvoiceManager::getSendResultLogText()} */
        'message_invoice_send' => '%1$s: %2$s has %3$s',
        'message_invoice_source' => 'Invoice for %1$s %2$s',
        /** {@see \Siel\Acumulus\Invoice\InvoiceManager::getSendResultLogText()} */
        'message_stock_transaction_send' => '%1$s(%2$s, %3$s): %4$s %5$s',
        'message_stock_transaction_source' => "Stock mutation for product '%1\$s': %2\$+f",
        /** {@see \Siel\Acumulus\Shop\\Siel\Acumulus\Shop\InvoiceManager::getSendResultLogText()} */
        /** {@see \Siel\Acumulus\Helpers\Result::getActionText()} */
        'action_unknown' => 'yet unknown',
        'action_sent' => 'has been sent',
        'action_not_sent' => 'has not been sent',
        /** {@see \Siel\Acumulus\Helpers\Result::getStatusMessages()} */
        'reason_not_sent_dry_run' => 'sending prevented by "dry run" option',
        'reason_unknown' => 'unknown reason: %d',
        'reason_not_sent_local_errors' => 'sending prevented by local errors',
        'reason_sent_test_mode' => 'test mode',
        /** {@see \Siel\Acumulus\Invoice\InvoiceAddResult::getStatusMessages()} */
        'reason_sent_new' => 'not yet sent',
        'reason_sent_new_status_change' => 'not yet sent and %1$s in [%2$s]',
        'reason_sent_forced' => 'forced',
        'reason_sent_lock_expired' => 'not yet sent because the previous attempt failed',
        /** {@see \Siel\Acumulus\Invoice\InvoiceAddResult::getStatusMessages()} */
        'reason_not_sent_alreadySent' => 'has already been sent',
        'reason_not_sent_triggerCreditNoteEvent_None' => 'option to automatically send credit notes not enabled',
        'reason_not_sent_wrongStatus' => 'wrong status: %1$s not in [%2$s]',
        'reason_not_sent_empty_invoice' => '0-amount invoice',
        'reason_not_sent_not_enabled_triggerInvoiceCreate' => 'option "send on creation of shop invoice" not enabled',
        'reason_not_sent_not_enabled_triggerInvoiceSent' => 'option "send on sending of shop invoice to customer" not enabled',
        'reason_not_sent_alreadySending' => 'already sending',
        'reason_not_sent_lockNotAcquired' => 'could not acquire lock',
        'reason_not_sent_no_invoice_lines' => 'invoice has no lines',
        'reason_not_sent_prevented_invoiceCreated' => 'sending prevented by event "AcumulusInvoiceCreated"',
        'reason_not_sent_prevented_invoiceCompleted' => 'sending prevented by event "AcumulusInvoiceSendBefore"',
        /** {@see \Siel\Acumulus\Product\StockTransactionResult::getStatusMessages()} */
        'reason_not_sent_not_enabled' => 'stock management not enabled',
        'reason_not_sent_disabled_product' => 'No stock managed for this product',
        'reason_not_sent_no_product' => 'Product not for found for invoice line',
        'reason_not_sent_zero_change' => 'No change in stock level',
        'reason_not_sent_no_value_to_match' => 'Search field for product is empty',
        'reason_not_sent_no_match_in_acumulus' => 'No product found in Acumulus for search value',
        'reason_not_sent_multiple_matches_in_acumulus' => 'Multiple products found for search value',
        'reason_sent' => 'everything OK',
    ];
}
