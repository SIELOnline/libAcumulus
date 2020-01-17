<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "Please rate our plugin" form.
 */
class RatePluginFormTranslations extends TranslationCollection
{
    protected $nl = array(
        'rate_acumulus_plugin' => "<p>Dankjewel dat je onze plugin voor Acumulus gebruikt!</p>
            <p>Je bespaart jezelf hier veel invoerwerk mee. Deze plugin is met veel zorg ontwikkeld en we blijven deze steeds beter maken maar is en blijft gratis.</p>
            <p>Heb jij ook voordeel van deze plugin? Laat het ons weten en geef je waardering op <a href='https://nl.wordpress.org/plugins/acumulus/'>onze pagina op de WordPress Plugin Directory</a> (log in en klik op \"Voeg mijn beoordeling toe\".</p>",
        'later' => 'Later',
        'done' => 'Ja, ik heb een rating gegeven',
        'done_thanks' => 'Bedankt voor het beoordelen van de Acumulus plugin.',
    );

    protected $en = array(
        'rate_acumulus_plugin' => "<p>Thanks for using our plugin!</p>
            <p>You save yourself a lot of time using this plugin. The Acumulus plugin has been developed with great care and we will continue to improve it but will remain free of charge.</p>
            <p>Do you also benefit from this plugin? Let us know and give your rating on <a href='https://wordpress.org/plugins/acumulus/'>our page on the WordPress Plugin Directory</a> (login en click on \"Add my review\".</p>",
        'later' => 'Not now',
        'done' => 'Yes, I have done so',
        'done_thanks' => 'Thanks for rating the Acumulus plugin.',
    );
}
