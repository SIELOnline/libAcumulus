<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the more Acumulus section on the config and registration forms.
 */
class MoreAcumulusTranslations extends TranslationCollection
{
    protected $nl = [
        // Information block.
        'informationBlockHeader' => 'Informatie over Acumulus, uw contract, en uw webshop met deze %1$s',
        'desc_environmentInformation' => 'Vermeld aub deze gegevens bij een supportverzoek.',

        'moreAcumulusTitle' => 'Meer Acumulus',
        'link_login' => '<a href="https://www.sielsystems.nl/" target="_blank">Inloggen op Acumulus</a>',
        'link_app' => '<a href="https://www.sielsystems.nl/app" target="_blank">Installeer de Acumulus app voor iPhone of Android</a>',
        'link_manual' => '<a href="https://wiki.acumulus.nl/" target="_blank">Lees de Online handleiding</a>',
        'link_forum' => '<a href="https://forum.acumulus.nl/index.php" target="_blank">Bezoek het Acumulus forum</a> waar u algemene vragen kunt stellen of de antwoorden op al gestelde vragen kunt opzoeken',
        'link_website' => '<a href="https://siel.nl/acumulus/" target="_blank">Bezoek onze website</a>',
        'link_support' => '<a href="mailto:%1$s?subject=%2$s&body=%3$s">Open een supportverzoek</a> (opent in uw e-mailprogramma)',
        'support_subject' => '[Ik heb een probleem met mijn Acumulus voor %1$s %2$s]',
        'support_body' => "[Omschrijf hier uw probleem, vermeld a.u.b. alle relevante informatie]\n\n[Stuur indien mogelijk en nodig, logbestanden mee: de PHP error log en de Acumulus log]\n",
        'regards' => 'Mvg,',
        'your_name' => '[Uw naam]',
        'contract' => 'Uw contract',
        'no_contract_data_local' => 'Contractdata nog niet ingevuld.',
        'no_contract_data' => 'Contractdata niet beschikbaar, meldingen:',
        'environment' => 'Uw Omgeving',

        'field_companyName' => 'Bedrijfsnaam',
        'field_code' => 'Contractcode',
        'contractEndDate' => 'Vervaldatum',
    ];

    protected $en = [
    ];
}
