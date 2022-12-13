<?php

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "Please rate our plugin" form.
 */
class RatePluginFormTranslations extends TranslationCollection
{
    protected array $nl = [
        'rate_acumulus_plugin' => '<p>Leuk dat je de %1$s voor Acumulus gebruikt!</p>
            <p>Wij hebben hard ons best gedaan om deze zo gebruiksvriendelijk mogelijk te maken. %2$s</p>',
        'review_on_marketplace' => 'Zou jij ons een review willen geven?',
        'do' => 'OK, breng me er heen',
        'later' => 'Liever niet nu',
        'done_thanks' => 'Bedankt voor het beoordelen van de Acumulus %1$s.',
        'no_problem' => 'OK, geen probleem.',
        'unknown_action' => "Onbekende actie '%s'",
    ];

    protected array $en = [
        'rate_acumulus_plugin' => '<p>Thank you so much for using the Acumulus %1$s!</p>
            <p>We tried really hard to provide you the best possible user experience. %2$s</p>',
        'review_on_marketplace' => 'Would you please give us a review?',
        'do' => 'OK, get me there',
        'later' => 'Not now',
        'done_thanks' => 'Thank you for taking the time to review the Acumulus %1$s.',
        'no_problem' => 'OK, no problem.',
        'unknown_action' => "Unknown action '%s'",
    ];
}
