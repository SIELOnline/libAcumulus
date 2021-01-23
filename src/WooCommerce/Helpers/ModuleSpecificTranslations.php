<?php
namespace Siel\Acumulus\Woocommerce\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = [
        'button_link' => '<a href="%2$s" class="button button-primary button-large">%1$s</a>',
        'see_post_meta' => 'Zie de tabel postmeta voor posts van het type "order" of "refund"',
        'meta_original_order_for_refund' => 'Post meta van de oorspronkelijke bestelling, alleen beschikbaar bij credit nota\'s',
        'wc2_end_support' => 'LET OP: de Acumulus plugin zal in een volgende versie stoppen met het ondersteunen van Woocommerce 2! Begin nu met het upgraden naar WooCommerce 4.',

        'Standaard' => 'Standaard',
        'vat_class_left_empty' => 'Ik zet "Btw status" op "Geen"',
        'desc_vatFreeClass' => 'Geef aan welke belastingklasse u gebruikt om aan te geven dat een product of dienst btw-vrij is.
Kies de eerste optie als u bij uw btw-vrije producten en diensten de "Btw status" op "Geen" zet.
Deze instelling hoeft u alleen in te vullen als u hierboven hebt aangegeven dat u niet "Alleen aan btw onderhevige producten en/of diensten." verkoopt.',

        // Invoice status overview: shorter labels due to very limited available space.
        'vat_type' => 'Soort',
        'foreign_vat' => 'EU btw',
        'foreign_national_vat' => '(EU) btw',
        'payment_status' => 'Status',
        'documents' => 'Pdf\'s',
        'document' => 'Pdf',

        // Rate our plugin message.
        'review_on_marketplace' => 'Zou jij ons een review willen geven op WordPress.org?',
        // These are the same for English thus no need to copy them.
        'module' => 'plugin',
        'review_url' => 'https://wordpress.org/support/plugin/acumulus/reviews/#new-post',
    ];

    protected $en = [
        'see_post_meta' => 'See the table postmeta for posts of the type "order" of "refund"',
        'meta_original_order_for_refund' => 'Post meta of the original order, only available with credit notes',
        'wc2_end_support' => 'NOTE: in a next version the Acumulus plugin will stop supporting WooCommerce 2! Start upgrading to wooCommerce 3 now.',

        'Standaard' => 'Standard', // WC uses standard tax rate, not default tax rate.
        'vat_class_left_empty' => 'I set "Tax status" to "None"',
        'desc_vatFreeClass' => 'Indicate which vat class you use to indicate that a product or service is VAT free.
Choose the first option if you do set the "Tax status" to "None" for your vat free products and services.
You only have to fill in this setting if above you did not select the option that you sell "Only products or services that are VAT liable."',

        // Invoice status overview: shorter labels due to available space.
        'vat_type' => 'Type',
        'payment_status' => 'Status',
        'documents' => 'Pdfs',
        'document' => 'Pdf',

        // Rate our plugin message.
        'review_on_marketplace' => 'Would you please give us a review on WordPress.org?',
    ];
}
