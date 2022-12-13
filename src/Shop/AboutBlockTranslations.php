<?php
/**
 * @noinspection HtmlUnknownTarget
 */

declare(strict_types=1);

namespace Siel\Acumulus\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the "About" section on the Acumulus forms.
 */
class AboutBlockTranslations extends TranslationCollection
{
    protected array $nl = [
        // About block.
        'informationBlockHeader' => 'Over',
        'informationBlockDescription' => 'Informatie over Acumulus, uw contract, uw support-opties, en uw webshop met deze %1$s',

        // About your contract.
        'contract' => 'Uw contract',
        'no_contract_data_local' => 'Contractdata nog niet ingevuld.',
        'no_contract_data' => 'Contractdata niet beschikbaar',
        'field_companyName' => 'Bedrijfsnaam',
        'field_code' => 'Contractcode',
        'contract_end_date' => 'Vervaldatum',
        'entries_about' => 'Aantal boekingen',
        'entries_numbers' => 'U heeft %1$d boekingen gedaan. U heeft een maximum van %2$d boekingen en kunt dus nog %3$d boekingen doen.',
        'email_status_label' => 'E-mail',
        'email_status_text' => 'Wij ondervinden afleverproblemen in onze communicatie naar u toe. Log in op Acumulus voor meer info of om te melden dat de problemen verholpen zijn.',
        'email_status_text_reason' => 'In onze communicatie naar u toe ontvingen wij deze melding: "%1$s". Log in op Acumulus voor meer info of om te melden dat de problemen verholpen zijn.',

        // Pro-support
        'pro_support_list_header' => 'Door u aangeschafte (en nog geldige) <span class="acumulus-blue">Pro-</span>support',
        'no_pro_support' => 'Nog niet aangeschaft of geen geldige <span class="acumulus-blue">Pro-</span>support meer.',
        'pro_support_line' => '"%1$s" voor %2$s van %3$s tot %4$s.',

        // About your webshop.
        'desc_environmentInformation' => 'Vermeld aub deze gegevens bij een supportverzoek.',

        // About your EU sales.
        'euCommerce' => 'EU verkopen',
        'no_eu_commerce_data' => 'Informatie over EU verkopen is niet beschikbaar',
        'info_block_eu_commerce_threshold_passed' => 'U bent de drempel van verkopen binnen de EU tot aan waar u Nederlandse btw mag berekenen gepasseerd. U dient vanaf nu, tot aan het eind van het jaar, op alle facturen naar particulieren of btw-vrijgestelden binnen de EU het btw tarief van het land van afname te berekenen. Pas direct uw webshop hierop aan.',
        'info_block_eu_commerce_threshold_warning' => 'U zit op %.1f%% van de drempel van verkopen binnen de EU tot aan waar u Nederlandse btw mag berekenen. Begin op tijd aan de voorbereidingen tot het aanpassen van de belastinginstellingen van uw webwinkel en overige verkoopkanalen.',
        'info_block_eu_commerce_threshold_ok' => 'U zit nog ruim onder de drempel van verkopen binnen de EU tot aan waar u Nederlandse btw mag berekenen.',

        // Pro-support
        'pro_support_header' => 'SIEL <span class="acumulus-blue">Pro-</span>support',
        'pro_support_info' => <<<LONGSTRING
<p>Met <a href="https://www.siel.nl/acumulus/koppelingen/support/" target="_blank">SIEL <span class="acumulus-blue">Pro-</span>support</a>
verzekert u zich van uitgebreide en snelle ondersteuning voor de Acumulus %4\$s in uw webshop.
Wij kunnen u helpen, niet alleen bij foutmeldingen, maar ook bij het correct instellen of aanpassen van uw webshop om te voldoen
aan fiscale regels.</p>
<p>Als u <span class="acumulus-blue">Pro-</span>support voor uw %4\$s hebt gekocht, ontvangt u een code waarmee u de
support-periode van 1 jaar via het <a href="%3\$s">activatieformulier</a> kunt activeren voor dit domein.
Heeft u nog geen pro-support aangeschaft of wilt u het verlengen? Verzeker jouw webshop %4\$s nu hier van uitgebreide ondersteuning:<br>
<a href="%2\$s" target="_blank" class="button-pro-support"><img alt="Siel webshop - schaf pro-support aan" src="%1\$s"></a></p>
LONGSTRING
        ,

        // Moore Acumulus links.
        'moreAcumulusTitle' => 'Meer Acumulus (links openen in een nieuwe tab)',
        'link_login' => '<a href="https://www.sielsystems.nl/" target="_blank">Inloggen op Acumulus</a>',
        'link_app' => '<a href="https://www.sielsystems.nl/app" target="_blank">Installeer de Acumulus app voor iPhone of Android</a>',
        'link_manual' => '<a href="https://wiki.acumulus.nl/" target="_blank">Lees de Online handleiding over Acumulus</a>',
        'link_website' => '<a href="https://siel.nl/" target="_blank">Bezoek de website van SIEL</a>',
        'link_buy_support' => '<a href="https://www.siel.nl/acumulus/koppelingen/support/" target="_blank"><span class="acumulus-blue">Pro-</span>support voor deze Acumulus %1$s</a>',
        'link_forum' => '<span class="acumulus-blue">Basic-</span>support: <a href="https://forum.acumulus.nl/index.php" target="_blank">Bezoek het Acumulus forum</a> waar u algemene vragen kunt stellen of de antwoorden op al gestelde vragen kunt opzoeken',
        'link_support' => '<span class="acumulus-blue">Pro-</span>support: <a href="mailto:%1$s?subject=%2$s&body=%3$s">Open een supportverzoek</a> (opent in uw e-mailprogramma)',
        'support_subject' => '[Ik heb een probleem met mijn Acumulus voor %1$s %2$s]',
        'support_body' => "[Omschrijf hier uw probleem, vermeld a.u.b. alle relevante informatie]\n\n[Stuur indien mogelijk en nodig deze logbestanden mee: de PHP error en de Acumulus log]\n",
        'regards' => 'Mvg,',
        'your_name' => '[Uw naam]',
    ];

    protected array $en = [
        // About block.
        'informationBlockHeader' => 'About',
        'informationBlockDescription' => 'Information about Acumulus, your contract, your support options, and your webshop with this %1$s',

        // About your contract.
        'contract' => 'Your contract',
        'no_contract_data_local' => 'Contract data not yet set.',
        'no_contract_data' => 'Contract data not available',
        'field_companyName' => 'Company name',
        'field_code' => 'Contract code',
        'contract_end_date' => 'Ends on',
        'entries_about' => 'Number of entries',
        'entries_numbers' => 'You have created %1$d entries out of your maximum of %2$d, so you can create yet %3$d more entries.',
        'email_status_label' => 'E-mail',
        'email_status_text' => 'We received errors on trying to communicate with you. Please log in on Acumulus for more info or to mark the problems as resolved.',
        'email_status_text_reason' => 'On trying to communicate with you, we received this message: "%1$s". Please log in on Acumulus for more info or to mark the problems as resolved.',

        // Pro-support
        'pro_support_list_header' => '(Still valid) <span class="acumulus-blue">Pro-</span>support bought by you',
        'no_pro_support' => 'Not yet bought or no longer valid <span class="acumulus-blue">Pro-</span>support',
        'pro_support_line' => '"%1$s" for %2$s from %3$s to %4$s.',

        // About your webshop.
        'desc_environmentInformation' => 'Please provide this information in case of a support request.',

        // About your EU sales.
        'euCommerce' => 'EU sales',
        'no_eu_commerce_data' => 'Information about EU sales is not available',
        'info_block_eu_commerce_threshold_passed' => 'You are above the threshold up to which you may charge Dutch VAT for EU customers. As of now, and up to the end of the year, you must charge EU VAT. Immediately change the VAT settings of your web shop and other sales channels.',
        'info_block_eu_commerce_threshold_warning' => 'You are at %.1f%% of the threshold up to which you may charge Dutch VAT for EU customers. Start preparing to change your VAT settings of your web shop and other sales channels.',
        'info_block_eu_commerce_threshold_ok' => 'You are still way below the threshold up to which you may charge Dutch VAT for EU customers.',

        // Moore Acumulus links.
        'moreAcumulusTitle' => 'More Acumulus (links open in a new tab)',
        'link_login' => '<a href="https://www.sielsystems.nl/" target="_blank">Login to Acumulus</a>',
        'link_app' => '<a href="https://www.sielsystems.nl/app" target="_blank">Install the Acumulus app for iPhone or Android</a>',
        'link_manual' => '<a href="https://wiki.acumulus.nl/" target="_blank">Read the online manual about Acumulus</a>',
        'link_forum' => '<a href="https://forum.acumulus.nl/index.php" target="_blank">Visit the Acumulus forum</a> where you can ask general questions or look up the answers to already asked questions.',
        'link_website' => '<a href="https://siel.nl/" target="_blank">Visit the SIEL website</a>',
        'link_buy_support' => '<a href="https://www.siel.nl/acumulus/koppelingen/support/" target="_blank"><span class="acumulus-blue">Pro-</span>support for this Acumulus %1$s</a>',
        'link_support' => '<a href="mailto:%1$s?subject=%2$s&body=%3$s">Open a support request</a> (opens in your mail app)',
        'support_subject' => '[I have a problem with my Acumulus for %1$s %2$s]',
        'support_body' => "[Please describe your problem here, include all relevant information]\n\n[If possible and necessary include these log files: the PHP error and the Acumulus log.]\n",
        'regards' => 'Regards,',
        'your_name' => '[Your name]',
    ];
}
