<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for {@see \Siel\Acumulus\Invoice\InvoiceAddMail}.
 */
class InvoiceAddMailTranslations extends TranslationCollection
{
    protected array $nl = [
        // Subject base.
        'mail_subject' => 'Factuur verzonden naar {module_name}',
        'mail_subject_concept' => 'Conceptfactuur verzonden naar {module_name}',

        // Subject result.
        'mail_subject_no_pdf' => 'geen pdf verstuurd',

        // Body intro.
        'mail_body_exception' => 'Bij het verzenden van een factuur naar {module_name} is er een ernstige fout opgetreden.',
        'mail_body_exception_not_created' => 'De factuur is niet aangemaakt in {module_name}.',
        'mail_body_exception_maybe_created' => 'De factuur is misschien aangemaakt, controleer dit in {module_name} zelf.',
        'mail_body_errors' => 'Bij het verzenden van een factuur naar {module_name} zijn er fouten opgetreden.',
        'mail_body_errors_not_created' => 'De factuur is niet aangemaakt in {module_name}. Pas de factuur aan in uw webshop alvorens deze opnieuw te versturen.',
        'mail_body_warnings' => 'Bij het verzenden van een factuur naar {module_name} zijn er waarschuwingen opgetreden.',
        'mail_body_warnings_created' => 'De factuur is aangemaakt, maar u dient deze in {module_name} te controleren en zonodig te corrigeren.',
        'mail_body_success' => 'Onderstaande factuur is succesvol naar {module_name} verstuurd.',

        'mail_body_test_mode' => 'De factuur is in testmodus verstuurd en is dus niet aan uw boekhouding toegevoegd.',
        'mail_body_concept' => 'De factuur is als concept aangemaakt. Controleer de factuur in {module_name}, waarna u deze alsnog definitief kan maken. U vindt conceptfacturen onder "Overzichten - Conceptfacturen en offertes".',

        'mail_body_pdf_enabled' => 'U laat {module_name} de factuur als pdf naar de klant versturen.',
        'mail_body_pdf_not_sent_errors' => 'Omdat de factuur fouten bevat en niet is aangemaakt is deze pdf niet verstuurd.',
        'mail_body_pdf_not_sent_concept' => 'Omdat de factuur als concept is aangemaakt is deze pdf niet verstuurd.',

        // Body about.
        'mail_about_header' => 'Over de factuur',
    ];

    protected array $en = [
        // Subject base.
        'mail_subject' => 'Invoice sent to {module_name}',
        'mail_subject_concept' => 'Concept invoice sent to {module_name}',

        // Subject result.
        'mail_subject_no_pdf' => 'no pdf was sent',

        // Body intro.
        'mail_body_exception' => 'Serious error on sending an invoice to {module_name}.',
        'mail_body_exception_not_created' => 'The invoice has not been created in {module_name}.',
        'mail_body_exception_maybe_created' => 'The invoice may have been created, but you\'ll have to check this yourself.',
        'mail_body_errors' => 'Errors on sending an invoice to {module_name}.',
        'mail_body_errors_not_created' => 'The invoice has not been created in {module_name}. Correct the invoice in your webshop before sending it again.',
        'mail_body_warnings' => 'Warnings on sending an invoice to {module_name}.',
        'mail_body_warnings_created' => 'The invoice has been created, but you have to check, and if necessary correct, it in {module_name}.',
        'mail_body_success' => 'The invoice below has successfully been sent to {module_name}.',

        'mail_body_test_mode' => 'The invoice has been sent in test mode and thus has not been added to your administration.',
        'mail_body_concept' => 'The invoice has been created as concept. Check the invoice in {module_name} before finalising it. you will find concept invoices at "Lists - Concept invoices and quotations".',

        'mail_body_pdf_enabled' => 'you have {module_name} send the invoice as a pdf to the client.',
        'mail_body_pdf_not_sent_errors' => 'Because the invoice contains errors, this pdf has not been sent.',
        'mail_body_pdf_not_sent_concept' => 'Because the invoice was created as concept, this pdf has not been sent.',

        // Body about.
        'mail_about_header' => 'About the invoice',
    ];
}
