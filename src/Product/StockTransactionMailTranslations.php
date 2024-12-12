<?php

declare(strict_types=1);

namespace Siel\Acumulus\Product;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for {@see \Siel\Acumulus\Product\StockTransactionMail}.
 */
class StockTransactionMailTranslations extends TranslationCollection
{
    protected array $nl = [
        // Subject base.
        'mail_subject' => 'Voorraadmutatie verzonden naar {module_name}',

        // Body intro.
        'mail_body_exception' => 'Bij het verzenden van een voorraadmutatie naar {module_name} is er een ernstige fout opgetreden.',
        'mail_body_exception_not_created' => 'De voorraadmutatie is niet verwerkt in {module_name}.',
        'mail_body_exception_maybe_created' => 'De voorraadmutatie is misschien verwerkt, controleer dit in {module_name} zelf.',
        'mail_body_errors' => 'Bij het verzenden van een voorraadmutatie naar {module_name} zijn er fouten opgetreden.',
        'mail_body_errors_not_created' => 'De voorraadmutatie is niet verwerkt in {module_name}.',
        'mail_body_errors_local' => 'De voorraadmutatie is niet verwerkt in {module_name}. Pas de zoekdata in uw webshop (of in {module_name}) aan.',
        'mail_body_warnings' => 'Bij het verzenden van een voorraadmutatie naar {module_name} zijn er waarschuwingen opgetreden.',
        'mail_body_warnings_created' => 'De voorraadmutatie is verwerkt, maar u dient deze in {module_name} te controleren en zonodig te corrigeren.',
        'mail_body_success' => 'Onderstaande voorraadmutatie is succesvol naar {module_name} verstuurd.',
        'mail_body_test_mode' => 'De voorraadmutatie is in testmodus verstuurd en is dus niet aan uw boekhouding toegevoegd.',

        // Body about.
        'mail_about_header' => 'Over de voorraadmutatie',
        'stock_level' => 'Voorraadniveau',

        // Body messages.
        'message_no_stock_returned' => 'De nieuwe voorraadhoogte is niet bekend',
    ];

    protected array $en = [
        // Subject base.
        'mail_subject' => 'Stock mutation sent to {module_name}}',

        // Body intro.
        'mail_body_exception' => 'Serious error on sending a stock mutation to {module_name}.',
        'mail_body_exception_not_created' => 'The stock mutation has not been processed in {module_name}.',
        'mail_body_exception_maybe_created' => 'The stock mutation may have been processed, but you\'ll have to check this yourself.',
        'mail_body_errors' => 'Errors on sending a stock mutation to {module_name}.',
        'mail_body_errors_not_created' => 'The stock mutation has not been processed in {module_name}.',
        'mail_body_errors_local' => 'The stock mutation has not been processed in {module_name}. Change your search data in either your webshop or {module_name} before trying again.',
        'mail_body_warnings' => 'Warnings on sending a stock mutation to {module_name}.',
        'mail_body_warnings_created' => 'The stock mutation has been processed, but you have to check, and if necessary correct, it in {module_name}.',
        'mail_body_success' => 'The stock mutation below has successfully been sent to {module_name}.',
        'mail_body_test_mode' => 'The stock mutation has been sent in test mode and thus has not been processed in your administration.',

        // Body about.
        'mail_about_header' => 'About the stock mutation',
        'stock_level' => 'Stock level',

        // Body messages.
        'message_no_stock_returned' => 'The new stock amount is not known.',
    ];
}
