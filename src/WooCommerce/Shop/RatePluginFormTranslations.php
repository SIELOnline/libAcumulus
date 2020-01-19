<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "Please rate our plugin" form.
 */
class RatePluginFormTranslations extends TranslationCollection
{
    protected $nl = array(
        'rate_acumulus_plugin' => "<p>Leuk dat je de plugin voor Acumulus gebruikt!</p>
            <p>Wij hebben hard ons best gedaan om deze zo gebruiksvriendelijk mogelijk te maken. Laat jij weten wat je er van vindt?</p>",
        'do' => 'OK, breng me er heen',
        'later' => 'Liever niet nu',
        'done_thanks' => 'Bedankt voor het beoordelen van de Acumulus plugin.',
        'review_url' => 'https://wordpress.org/support/plugin/acumulus/reviews/#new-post'
    );

    protected $en = array(
        'rate_acumulus_plugin' => "<p>Thank you so much for using the Acumulus plugin!</p>
            <p>We tried really hard to provide you the best possible user experience. Would you please let us know your opinion?</p>",
        'do' => 'OK, get me there',
        'later' => 'Not now',
        'done_thanks' => 'Thank you for taking the time to review the plugin.',
        'review_url' => 'https://wordpress.org/support/plugin/acumulus/reviews/#new-post'
    );
}
