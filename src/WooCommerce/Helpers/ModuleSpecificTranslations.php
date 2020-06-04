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

        // Invoice status overview: shorter labels due to available space.
        'vat_type' => 'Soort',
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

        // Invoice status overview: shorter labels due to available space.
        'vat_type' => 'Type',
        'payment_status' => 'Status',
        'documents' => 'Pdfs',
        'document' => 'Pdf',

        // Rate our plugin message.
        'review_on_marketplace' => 'Would you please give us a review on WordPress.org?',
    ];
}
