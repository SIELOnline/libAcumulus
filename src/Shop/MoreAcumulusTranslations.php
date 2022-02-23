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
        'link_manual' => '<a href="https://wiki.acumulus.nl/" target="_blank">Lees de Online handleiding over Acumulus</a>',
        'link_forum' => '<a href="https://forum.acumulus.nl/index.php" target="_blank">Bezoek het Acumulus forum</a> waar u algemene vragen kunt stellen of de antwoorden op al gestelde vragen kunt opzoeken',
        'link_website' => '<a href="https://siel.nl/" target="_blank">Bezoek de website van SIEL</a>',
        'link_support' => '<a href="mailto:%1$s?subject=%2$s&body=%3$s">Open een supportverzoek</a> (opent in uw e-mailprogramma)',
        'support_subject' => '[Ik heb een probleem met mijn Acumulus voor %1$s %2$s]',
        'support_body' => "[Omschrijf hier uw probleem, vermeld a.u.b. alle relevante informatie]\n\n[Stuur indien mogelijk en nodig deze logbestanden mee: de PHP error en de Acumulus log]\n",
        'regards' => 'Mvg,',
        'your_name' => '[Uw naam]',
        'contract' => 'Uw contract',
        'no_contract_data_local' => 'Contractdata nog niet ingevuld.',
        'no_contract_data' => 'Contractdata niet beschikbaar, meldingen:',
        'euCommerce' => 'EU verkopen',
        'no_eu_commerce_data' => 'Informatie over EU verkopen niet beschikbaar, meldingen:',
        'environment' => 'Over uw webwinkel',

        'field_companyName' => 'Bedrijfsnaam',
        'field_code' => 'Contractcode',
        'contract_end_date' => 'Vervaldatum',
        'entries_about' => 'Aantal boekingen',
        'entries_numbers' => 'U heeft %1$d boekingen gedaan. U heeft een maximum van %2$d boekingen en kunt dus nog %3$d boekingen doen.',
        'email_status_label' => 'E-mail',
        'email_status_text' => 'Wij ondervinden afleverproblemen in onze communicatie naar u toe. Log in op Acumulus voor meer info of om te melden dat de problemen verholpen zijn.',
        'email_status_text_reason' => 'In onze communicatie naar u toe ontvingen wij deze melding: "%1$s". Log in op Acumulus voor meer info of om te melden dat de problemen verholpen zijn.',

        'info_block_eu_commerce_threshold_passed' => 'U bent de drempel van verkopen binnen de EU tot aan waar u Nederlandse btw mag berekenen gepasseerd. U dient vanaf nu, tot aan het eind van het jaar, op alle facturen naar particulieren of btw-vrijgestelden binnen de EU het btw tarief van het land van afname te berekenen. Pas direct uw webshop hierop aan.',
        'info_block_eu_commerce_threshold_warning' => 'U zit op %.1f%% van de drempel van verkopen binnen de EU tot aan waar u Nederlandse btw mag berekenen. Begin op tijd aan de voorbereidingen tot het aanpassen van de belastinginstellingen van uw webwinkel en overige verkoopkanalen.',
        'info_block_eu_commerce_threshold_ok' => 'U zit nog ruim onder de drempel van verkopen binnen de EU tot aan waar u Nederlandse btw mag berekenen.',
    ];

    protected $en = [
        // Information block.
        'informationBlockHeader' => 'Information about Acumulus, your contract, and your webshop with this %1$s',
        'desc_environmentInformation' => 'Please provide this information in case of a support request.',

        'moreAcumulusTitle' => 'More Acumulus (links open in a new tab)',
        'link_login' => '<a href="https://www.sielsystems.nl/" target="_blank">Login to Acumulus</a>',
        'link_app' => '<a href="https://www.sielsystems.nl/app" target="_blank">Install the Acumulus app for iPhone or Android</a>',
        'link_manual' => '<a href="https://wiki.acumulus.nl/" target="_blank">Read the online manual about Acumulus</a>',
        'link_forum' => '<a href="https://forum.acumulus.nl/index.php" target="_blank">Visit the Acumulus forum</a> where you can ask general questions or look up the answers to already asked questions.',
        'link_website' => '<a href="https://siel.nl/" target="_blank">Visit the SIEL website</a>',
        'link_support' => '<a href="mailto:%1$s?subject=%2$s&body=%3$s">Open a support request</a> (opens in your mail app)',
        'support_subject' => '[I have a problem with my Acumulus for %1$s %2$s]',
        'support_body' => "[Please describe your problem here, include all relevant information]\n\n[If possible and necessary include these log files: the PHP error and the Acumulus log.]\n",
        'regards' => 'Regards,',
        'your_name' => '[Your name]',
        'contract' => 'Your contract',
        'no_contract_data_local' => 'Contract data not yet set.',
        'no_contract_data' => 'Contract data not available, messages:',
        'environment' => 'About your webshop',

        'field_companyName' => 'Company name',
        'field_code' => 'Contract code',
        'contract_end_date' => 'Ends on',
        'entries_about' => 'Number of entries',
        'entries_numbers' => 'You have created %1$d entries out of your maximum of %2$d, so you can create yet %3$d more entries.',
        'email_status_label' => 'E-mail',
        'email_status_text' => 'We received errors on trying to communicate with you. Please log in on Acumulus for more info or to mark the problems as resolved.',
        'email_status_text_reason' => 'On trying to communicate with you, we received this message: "%1$s". Please log in on Acumulus for more info or to mark the problems as resolved.',

        'info_block_eu_commerce_threshold_passed' => 'You are above the threshold up to which you may charge Dutch VAT for EU customers. As of now, and up to the end of the year, you must charge EU VAT. Immediately change the VAT settings of your web shop and other sales channels.',
        'info_block_eu_commerce_threshold_warning' => 'You are at %.1f%% of the threshold up to which you may charge Dutch VAT for EU customers. Start preparing to change your VAT settings of your web shop and other sales channels.',
        'info_block_eu_commerce_threshold_ok' => 'You are still way below the threshold up to which you may charge Dutch VAT for EU customers.',
    ];
}
