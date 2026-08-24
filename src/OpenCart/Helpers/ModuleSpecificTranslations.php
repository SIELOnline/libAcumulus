<?php
/**
 * @noinspection LongLine
 * @noinspection HtmlUnknownTarget
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Helpers;

use Siel\Acumulus\Helpers\ModuleSpecificTranslations as BaseModuleSpecificTranslations;

/**
 * Contains plugin specific overrides.
 *
 * @noinspection PhpUnused
 */
class ModuleSpecificTranslations extends BaseModuleSpecificTranslations
{
    protected array $nl = [
        'module' => 'extensie',
        'button_link' => '<a href="%2$s" class="button btn btn-primary"><i class="fa fa-cog"></i> %1$s</a>',

        'desc_triggerSettings' => 'Met behulp van deze instelling kunt u aangeven op welk(e) moment(en) u de factuur voor een bestelling naar Acumulus wilt versturen. Als u meerdere momenten selecteert, wordt de factuur naar Acumulus verstuurd zodra de bestelling één van de gekozen statussen bereikt. Een factuur zal altijd slechts 1 keer naar Acumulus worden verstuurd. Deze koppeling gebruikt alleen gegevens van de bestelling, dus u kunt elke status kiezen. De factuur van de webwinkel hoeft dus nog niet aangemaakt te zijn, tenzij u voor factuurdatum en nummer de gegevens van die factuur van de webwinkel wilt gebruiken. Als u voor "Niet automatisch versturen" kiest, dient u de facturen zelf over te zetten m.b.v. het <a href="%s">Acumulus batch-formulier</a>.',

        'vat_class' => 'belastinggroep',
        'vat_classes' => 'belastinggroepen',

        'fiscal_address_setting' => 'Systeem » Lokalisatie » Belastingen » Belastingklassen » wijzig een belastingklasse » kolom "gebaseerd op"',
    ];

    protected array $en = [
        'module' => 'extension',

        'desc_triggerSettings' => 'This setting determines at what instants the invoice for an order should be sent to Acumulus. If you select multiple instants, the invoice wil be sent as soon as the order reaches one of the selected statuses. Note that an invoice will only be sent once to Acumulus. This extension only uses order data, so you may select any status, the webshop invoice does not already have to be created,unless you want to use the webshop\'s invoice date and number as invoice date and number for the Acumulus invoice. If you select "Do not send automatically" you will have to use the <a href="%s">Acumulus batch send form</a>.',

        'vat_class' => 'tax class',
        'vat_classes' => 'tax classes',

        'fiscal_address_setting' => 'System » Localisation » Taxes » Tax classes » edit a tax class » column "based on"',
    ];
}
