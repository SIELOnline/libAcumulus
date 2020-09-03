<?php
namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "More Acumulus" section on the config and
 * register forms.
 */
class MoreAcumulusTranslations extends TranslationCollection
{
    protected $nl = [
        // Information block.
        'informationBlockHeader' => 'Informatie over Acumulus, uw contract, en uw webshop met deze %1$s',
        'desc_environmentInformation' => 'Vermeld aub deze gegevens bij een supportverzoek.',

        'moreAcumulusTitle' => 'Meer Acumulus (links openen in een nieuwe tab)',
        'link_login' => '<a href="https://www.sielsystems.nl/" target="_blank">Inloggen op Acumulus</a>',
        'link_app' => '<a href="https://www.sielsystems.nl/app" target="_blank">Installeer de Acumulus app voor iPhone of Android</a>',
        'link_manual' => '<a href="https://wiki.acumulus.nl/" target="_blank">Lees de Online handleiding</a>',
        'link_forum' => '<a href="https://forum.acumulus.nl/index.php" target="_blank">Bezoek het Acumulus forum</a> waar u algemene vragen kunt stellen of de antwoorden op al gestelde vragen kunt opzoeken',
        'link_website' => '<a href="https://siel.nl/acumulus/" target="_blank">Bezoek onze website</a>',
        'link_support' => '<a href="mailto:%1$s?subject=%2$s&body=%3$s">Open een supportverzoek</a> (opent in uw e-mailprogramma)',
        'support_subject' => '[Ik heb een probleem met mijn Acumulus voor %1$s %2$s]',
        'support_body' => "[Omschrijf hier uw probleem, vermeld a.u.b. alle relevante informatie]\n\n[Stuur indien mogelijk en nodig deze logbestanden mee: de PHP error en de Acumulus log]\n",
        'regards' => 'Mvg,',
        'your_name' => '[Uw naam]',
        'contract' => 'Uw contract',
        'no_contract_data_local' => 'Contractdata nog niet ingevuld.',
        'no_contract_data' => 'Contractdata niet beschikbaar, meldingen:',
        'environment' => 'Over uw webwinkel',

        'field_companyName' => 'Bedrijfsnaam',
        'field_code' => 'Contractcode',
        'contractEndDate' => 'Vervaldatum',
    ];

    protected $en = [
        // Information block.
        'informationBlockHeader' => 'Information about Acumulus, your contract, and your webshop with this %1$s',
        'desc_environmentInformation' => 'Please provide this information in case of a support request.',

        'moreAcumulusTitle' => 'More Acumulus (links open in a new tab)',
        'link_login' => '<a href="https://www.sielsystems.nl/" target="_blank">Login to Acumulus</a>',
        'link_app' => '<a href="https://www.sielsystems.nl/app" target="_blank">Install the Acumulus app for iPhone or Android</a>',
        'link_manual' => '<a href="https://wiki.acumulus.nl/" target="_blank">Read the online manual</a>',
        'link_forum' => '<a href="https://forum.acumulus.nl/index.php" target="_blank">Visit the Acumulus forum</a> where you can ask general questions or look up the answers to already asked questions.',
        'link_website' => '<a href="https://siel.nl/acumulus/" target="_blank">Visit our website</a>',
        'link_support' => '<a href="mailto:%1$s?subject=%2$s&body=%3$s">Open a support request</a> (opens in your mail app)',
        'support_subject' => '[I have a problem with my Acumulus for %1$s %2$s]',
        'support_body' => "[Please describe your problem here, include all relevant information]\n\n[If possible and necessary include these log files: the PHP error and the Acumulus log.]\n",
        'regards' => 'Regards,',
        'your_name' => '[Your name]',
        'contract' => 'Your contract',
        'no_contract_data_local' => 'Contract data not yet completed.',
        'no_contract_data' => 'Contract data not available, messages:',
        'environment' => 'About your webshop',

        'field_companyName' => 'Company name',
        'field_code' => 'Contract code',
        'contractEndDate' => 'Ends on',
    ];
}
