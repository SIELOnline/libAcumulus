<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

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

        'Standaard' => 'Standaard',
        'vat_class_left_empty' => 'Ik zet "Btw status" op "Geen"',
        'desc_vatFreeClass' => 'Geef aan welke %1$s u gebruikt om aan te geven dat een product of dienst btw-vrij is.<br>
Kies de 1e optie ("%2$s") als u geen btw-vrije producten of diensten aanbiedt.<br>
Kies de 2e optie ("%3$s") als u bij uw btw-vrije producten en diensten de "Btw status" op "Geen" zet.<br>
LET OP: het gaat er om of het product of de dienst btw-vrij is, uw bedrijf voor de <a href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/hoe_werkt_de_btw/nieuwe-kleineondernemersregeling/kleineondernemersregeling" target="_blank"">KOR regeling</a> heeft gekozen, of een btw vrijstelling heeft. Niet of u voor specifieke situaties een factuur zonder btw opstelt.
Ook is er een verschil met het 0%%-tarief hieronder, Dit verschil zit hem met name in de mogelijkheid tot aftrek van voorbelasting.',

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

        'Standaard' => 'Standard', // WC uses standard tax rate, not default tax rate.
        'vat_class_left_empty' => 'I set "Tax status" to "None"',
        'desc_vatFreeClass' => 'Indicate which %1$s you use to indicate that a product or service is VAT free.<br>
Select the 1st option ("%2$s") if you do not sell VAT free goods or services.<br>
Select the 2nd option ("%3$s") if you set the field "VAT status" to "None" on VAT free products.<br>
NOTE: this setting concerns whether the goods or services you offer are inherently VAT free, or because your company has chosen to use the <a href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/hoe_werkt_de_btw/nieuwe-kleineondernemersregeling/kleineondernemersregeling" target="_blank"">KOR regulations (in Dutch)</a>, or is for some other reason not VAT liable. Not whether you create an invoice with no or reversed VAT.
Also note that VAT free differs from the 0%% VAT rate below. This difference mainly concerns the right to deduct VAT paid on your purchases.',

        // Invoice status overview: shorter labels due to available space.
        'vat_type' => 'Type',
        'payment_status' => 'Status',
        'documents' => 'Pdfs',
        'document' => 'Pdf',

        // Rate our plugin message.
        'review_on_marketplace' => 'Would you please give us a review on WordPress.org?',
    ];
}
