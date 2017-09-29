<?php
namespace Siel\Acumulus\Woocommerce\Helpers;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains plugin specific overrides.
 */
class ModuleSpecificTranslations extends TranslationCollection
{
    protected $nl = array(
        'button_link' => '<a href="%2$s" class="button button-primary button-large">%1$s</a>',
        'see_post_meta' => 'Zie de tabel postmeta voor posts van het type "order" of "refund"',
        'meta_original_order_for_refund' => 'Post meta van de oorspronkelijke bestelling, alleen beschikbaar bij credit nota\'s',
    );

    protected $en = array(
        'see_post_meta' => 'See the table postmeta for posts of the type "order" of "refund"',
        'meta_original_order_for_refund' => 'Post meta of the original order, only available with credit notes',
    );
}
