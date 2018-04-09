<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the shop order status overview form.
 */
class ShopOrderOverviewFormTranslations extends TranslationCollection
{
    protected $nl = array(
        'acumulus_invoice' => 'Factuur in Acumulus',
        ShopOrderOverviewForm::Status_NotSent => 'Nog niet verzonden.',
        ShopOrderOverviewForm::Status_Sent => 'Factuur %1$s van %2$s',
        ShopOrderOverviewForm::Status_SentConcept => 'Op %1$s als concept verzonden.',
        ShopOrderOverviewForm::Status_Deleted => 'Verzonden op %1$s maar vervolgens op %2$s naar de prullenbak verplaatst.',
        ShopOrderOverviewForm::Status_NonExisting => 'Verzonden op %1$s maar niet meer bestaand in Acumulus, ook niet in de prullenbak.',
        ShopOrderOverviewForm::Status_CommunicationError => 'Verzonden maar door een communicatiefout met Acumulus kunnen we niet meer informatie tonen. Probeer het later nog eens.',
        'status_unknown' => "Onbekende status '%s'",

        'send_now' => 'Nu verzenden',
        'send_again' => 'Opnieuw verzenden',
        'undelete' => 'Herstel verwijderde boeking',

        'unknown' => 'onbekend',
        'concept_description' => 'Helaas kan van een conceptfactuur niet meer informatie getoond worden, ook niet als u deze definitief gemaakt heeft.',
        'messages' => 'Meldingen',

        'invoice_number' => 'Nummer',
        'invoice_date' => 'Datum',

        'vat_type' => 'Type',
        'vat_type_1' => 'Normaal',
        'vat_type_2' => 'BTW-verlegd binnen Nederland;',
        'vat_type_3' => 'BTW-verlegd naar ondernemer in de EU',
        'vat_type_4' => 'Goederen buiten de EU',
        'vat_type_5' => 'Margeregeling (tweedehands producten)',
        'vat_type_6' => 'Electronische diensten aan particulieren in de EU',

        'payment_status' => 'Status',
        'payment_status_1' => 'Nog niet betaald',
        'payment_status_2' => 'Betaald',
        'payment_status_2_date' => 'Betaald op %1$s',
        'payment_date' => 'Betaaldatum',

        'set_paid' => 'Zet op Betaald',
        'set_due' => 'Zet op Niet betaald',

        'invoice_amount' => 'Bedrag',

        'documents' => 'Documenten',
        'invoice' => 'Factuur',
        'packing_slip' => 'Pakbon',
        'open_as_pdf' => 'Acumulus %1$s openen als pdf',
    );

    protected $en = array(
        // Locales
        'nld' => 'C',
        'nl_NL' => 'C',

    );
}
