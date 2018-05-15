<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the shop order status overview form.
 */
class ShopOrderOverviewFormTranslations extends TranslationCollection
{
    protected $nl = array(
        // Invoice status.
        'acumulus_invoice_title' => 'Acumulus',
        ShopOrderOverviewForm::Invoice_NotSent => 'Nog niet verzonden',
        ShopOrderOverviewForm::Invoice_Sent => 'Factuur %1$s van %2$s',
        ShopOrderOverviewForm::Invoice_SentConcept => 'Op %1$s als concept verzonden',
        ShopOrderOverviewForm::Invoice_Deleted => 'Verzonden op %1$s maar vervolgens op %2$s naar de prullenbak verplaatst.',
        ShopOrderOverviewForm::Invoice_NonExisting => 'Verzonden op %1$s maar niet meer bestaand in Acumulus, ook niet in de prullenbak.',
        ShopOrderOverviewForm::Invoice_CommunicationError => 'Verzonden, maar door een communicatiefout met Acumulus kunnen we niet meer informatie tonen. Probeer het later nog eens.',
        'invoice_status_ok' => "De factuur lijkt in orde, er zijn geen onregelmatigheden gevonden",
        'concept_description' => 'Helaas kan van een conceptfactuur niet meer informatie getoond worden, ook niet als u deze definitief gemaakt heeft.',
        'messages' => 'Meldingen',
        'invoice_status_unknown' => "Onbekende status '%s'",
        'unknown' => 'onbekend',

        // Vat type.
        'vat_type' => 'Soort',
        'vat_type_1' => 'Normaal, Nederlandse btw',
        'vat_type_2' => 'Btw-verlegd binnen Nederland',
        'vat_type_3' => 'Btw-verlegd in de EU',
        'vat_type_4' => 'Goederen buiten de EU',
        'vat_type_5' => 'Margeregeling (2e-hands producten)',
        'vat_type_6' => 'Electronische diensten in de EU',

        // Amounts.
        'invoice_amount' => 'Bedrag',
        'amount_status_2' => 'Verschil minder dan 2 cent, waarschijnlijk een afrondingsfout',
        'amount_status_3' => 'Verschil tussen de 2 en 5 cent, waarschijnlijk een fout',
        'amount_status_4' => 'Verschil meer dan 5 cent: fout!',

        // Payment status.
        'payment_status' => 'Status',
        'payment_status_1' => 'Nog niet betaald',
        'payment_status_2' => 'Betaald',
        'payment_status_2_date' => 'Betaald op %1$s',
        'payment_date' => 'Betaaldatum',
        'payment_status_not_equal' => 'De betaalstatus in Acumulus komt niet overeen met die in uw webshop',

        // Actions.
        'send_now' => 'Nu verzenden',
        'send_again' => 'Opnieuw verzenden',
        'undelete' => 'Herstel verwijderde boeking',
        'set_paid' => 'Zet op Betaald',
        'set_due' => 'Zet op Niet betaald',

        // Links to pdf documents.
        'documents' => 'Pdf\'s',
        'invoice' => 'factuur',
        'packing_slip' => 'pakbon',
        'open_as_pdf' => 'Acumulus %1$s openen als pdf',
    );

    protected $en = array(
        // Invoice status.
        'acumulus_invoice_title' => 'Acumulus',
        ShopOrderOverviewForm::Invoice_NotSent => 'Not yet sent',
        ShopOrderOverviewForm::Invoice_Sent => 'Invoice %1$s of %2$s',
        ShopOrderOverviewForm::Invoice_SentConcept => 'On %1$s sent as concept',
        ShopOrderOverviewForm::Invoice_Deleted => 'Sent on %1$s, but subsequently on %2$s moved to the trash bin.',
        ShopOrderOverviewForm::Invoice_NonExisting => 'Sent on %1$s, but no longer existing in Acumulus, not even in the thrash bin.',
        ShopOrderOverviewForm::Invoice_CommunicationError => 'Sent, but due to a communication error we cannot show more information. Try again later.',
        'invoice_status_ok' => "The invoice seems to be fine, no irregularities were found",
        'concept_description' => 'Unfortunately, we cannot show more information about a concept invoice, not even when it has been made definitive.',
        'messages' => 'Messages',
        'invoice_status_unknown' => "Unknown status '%s'",
        'unknown' => 'unknown',

        // Vat type.
        'vat_type' => 'Type',
        'vat_type_1' => 'Normal, Dutch vat',
        'vat_type_2' => 'Reversed vat within the Netherlands',
        'vat_type_3' => 'Reversed vat within the EU',
        'vat_type_4' => 'Goods outside the EU',
        'vat_type_5' => 'Margin invoice (2nd hand goods)',
        'vat_type_6' => 'Electronic services within the EU',

        // Amounts.
        'invoice_amount' => 'Amount',
        'amount_status_2' => 'Difference less than 2 cents, probably a rounding error',
        'amount_status_3' => 'Difference between 2 and 5 cents, probably an error',
        'amount_status_4' => 'Difference more than 5 cents: error!',

        // Payment status.
        'payment_status' => 'Status',
        'payment_status_1' => 'Due',
        'payment_status_2' => 'Paid',
        'payment_status_2_date' => 'Paid on %1$s',
        'payment_status_not_equal' => 'The payment state in Acumulus differs from the one in your webshop',

        // Actions.
        'send_now' => 'Send now',
        'send_again' => 'Send again',
        'undelete' => 'Restore deleted invoice',
        'payment_date' => 'Payment date',
        'set_paid' => 'Set paid',
        'set_due' => 'Set due',

        // Links to pdf documents.
        'documents' => 'Docs',
        'invoice' => 'invoice',
        'packing_slip' => 'packing slip',
        'open_as_pdf' => 'Open Acumulus %1$s as pdf',
    );
}
