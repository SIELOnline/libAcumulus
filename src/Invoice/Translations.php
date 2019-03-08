<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Invoice namespace.
 */
class Translations extends TranslationCollection
{
    const  sharedNl = 'De factuur is als concept opgeslagen. In Acumulus zijn deze onder "Overzichten » Concept-facturen / offertes" terug te vinden. Controleer en corrigeer daar de factuur.';
    protected $nl = array(
        Source::Order => 'bestelling',
        Source::CreditNote => 'creditnota',
        Source::Other => 'overig',

        'plural_Order'  => 'bestellingen',
        'plural_CreditNote' => 'creditnota\'s',
        'plural_Other' => 'overige',
        'plural_Order_ref'  => 'bestellingreferenties',
        'plural_CreditNote_ref' => 'creditnotareferenties',
        'plural_Order_id'  => 'bestellingnummers',
        'plural_CreditNote_id' => 'creditnotanummers',

        'for' => 'voor',
        'vat' => 'btw',
        'inc_vat' => 'incl. btw',
        'ex_vat' => 'excl. btw',
        'shipping_costs' => 'Verzendkosten',
        // @nth: try to better distinguish free shipping and pickup: for now only WC, HS, and PS do so
        'free_shipping' => 'Gratis verzending',
        'pickup' => 'Afhalen',
        'payment_costs' => 'Betalingskosten',
        'discount' => 'Korting',
        'discount_code' => 'Kortingscode',
        'coupon_code' => 'Cadeaubon',
        'used' => 'gebruikt',
        'gift_wrapping' => 'Cadeauverpakking',
        'fee' => 'Behandelkosten',
        'refund' => 'Terugbetaling',
        'refund_adjustment' => 'Aanpassing teruggaafbedrag',
        'fee_adjustment' => 'Kosten (soort onbekend)',
        'discount_adjustment' => 'Handmatige korting',

        'message_warning_no_email' => 'De factuur bevat geen e-mailadres van de klant. Hierdoor kan er geen relatie in Acumulus aangemaakt of bijgewerkt worden. U kunt zelf in Acumulus een andere relatie aan deze factuur koppelen.',
        'message_warning_no_vatrate' => 'Een van de bestelregels bevat een foutief btw percentage. ' . self::sharedNl,
        'message_warning_no_vattype' => 'Het factuurtype (https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland) kan niet bepaald worden. ' . self::sharedNl,
        'message_warning_multiple_vattypes' => 'Er zijn meerdere factuurtypes (https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland) mogelijk. ' . self::sharedNl,
        'message_warning_no_vattype_at_all' => '%1$s Dit is hoogstwaarschijnlijk het gevolg van: bestelregels die ten onrechte geen btw hebben; foute "Over uw winkel" instellingen; of verkeerd ingestelde btw tarieven. Controleer deze.',
        'message_warning_no_vattype_incorrect_lines' => '%1$s Dit is hoogstwaarschijnlijk het gevolg van: enkele (handmatig ingevoerde) bestelregels die ten onrechte geen btw hebben; of foute "Over uw winkel" instellingen, controleer deze.',
        'message_warning_no_vattype_may_split' => '%1$s Hoogstwaarschijnlijk dient u de factuur te splitsen in verschillende factuurtypes. Echter, controleer voor de zekerheid ook de "Over uw winkel" instellingen.',
        'message_warning_no_vattype_must_split' => '%1$s U dient de factuur te splitsen in verschillende factuurtypes.',
        'message_warning_no_vattype_multiple_possible' => '%1$s Waarschijnlijk komt dit door een gebrek aan informatie over de aard van de bestelde artikelen. Het volledig invullen van de "Over uw winkel" instellingen zou kunnen helpen.',
        'message_warning_line_without_vat' => 'Één of meer van de factuurregels hebben geen btw terwijl is ingesteld dat er "Alleen aan btw onderhevige producten en/of diensten" aangeboden worden. ' . self::sharedNl,
        'message_warning_strategies_failed' => 'Door een fout in uw instellingen of btw tarieven, konden niet alle fatuurregels correct gecompleteerd worden. ' . self::sharedNl,
        'message_warning_missing_amount_added' => 'Het factuurbedrag klopt niet met het totaal van de regels. Daarom is er een correctieregel toegevoegd met een bedrag (ex. btw) van €%1$.2f en een btw bedrag van €%2$.2f. ' . self::sharedNl,
        'message_warning_missing_amount_warn' => 'Het factuurbedrag klopt niet met het totaal van de regels. %1$s. ' . self::sharedNl,
        'message_warning_missing_amount_spec' => 'het %1$s wijkt €%2$.2f af',
        'amount_ex' => 'bedrag (ex. btw)',
        'amount_inc' => 'bedrag (incl. btw)',
        'amount_vat' => 'btw-bedrag',
        'message_warning_no_pdf' => 'Vanwege deze waarschuwing is er ook geen PDF factuur naar de klant verstuurd. U dient dit handmatig alsnog te doen.',
        'message_warning_old_entry_deleted' => 'De factuur heeft een oudere boeking voor deze %1$s in Acumulus overschreven. Deze oudere boeking met boeknummer %2$d is in Acumulus naar de prullenbak verplaatst en is daar nu terug te vinden onder "Overzichten » Laatste boekingen » Verwijderde boekingen".',
        'message_warning_old_entry_not_deleted' => 'De factuur heeft een oudere boeking voor deze %1$s in Acumulus overschreven. We hebben geprobeerd deze oudere boeking met boeknummer %2$d in Acumulus naar de prullenbak te verplaatsen, maar dit is niet gelukt omdat de oude factuur waarschijnlijk al verwijderd was. Deze oude factuur kan misschien nog steeds teruggevonden worden onder "Overzichten » Laatste boekingen » Verwijderde boekingen".',
    );

    protected $en = array(
        Source::Order => 'order',
        Source::CreditNote => 'credit note',
        Source::Other => 'other',

        'plural_Order'  => 'orders',
        'plural_CreditNote' => 'credit notes',
        'plural_Other' => 'other',
        'plural_Order_ref'  => 'order references',
        'plural_CreditNote_ref' => 'credit note references',
        'plural_Order_id'  => 'order numbers',
        'plural_CreditNote_id' => 'credit note numbers',

        'for' => 'for',
        'vat' => 'VAT',
        'inc_vat' => 'incl. VAT',
        'ex_vat' => 'ex. VAT',
        'shipping_costs' => 'Shipping costs',
        'free_shipping' => 'Free shipping',
        'pickup' => 'In store pick-up',
        'payment_costs' => 'Payment fee',
        'discount' => 'Discount',
        'discount_code' => 'Coupon code',
        'coupon_code' => 'Voucher',
        'used' => 'used',
        'gift_wrapping' => 'Gift wrapping',
        'fee' => 'Order treatment costs',
        'refund' => 'Refund',
        'refund_adjustment' => 'Refund adjustment',
        'fee_adjustment' => 'Fee (unknown type)',
        'discount_adjustment' => 'Manual discount',

        'message_warning_no_email' => 'The invoice does not have a customer email address. Therefore we could not create or update a relation in Acumulus. You can connect a relation to this invoice yourself.',
        'message_warning_no_vatrate' => 'One of the invoice lines contains an invalid vat rate. The invoice has been saved as concept. Correct the invoice in Acumulus.',
        'message_warning_no_vattype' => 'The invoice type for the invoice (https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland) could not be determined. The invoice has been saved as concept. Correct the invoice in Acumulus.',
        'message_warning_multiple_vattypes' => 'The invoice can have multiple vat types (https://wiki.acumulus.nl/index.php?page=facturen-naar-het-buitenland) . The invoice has been saved as concept. Correct the invoice in Acumulus.',
        'message_warning_no_vattype_at_all' => '%1$s This is very likely caused by: order lines incorrectly not having vat; incorrect "About your shop" settings; or incorrect vat rates. Check these.',
        'message_warning_no_vattype_incorrect_lines' => '%1$s This is very likely caused by: some (manually entered) order lines incorrectly not having vat; or incorrect "About your shop" settings, check these.',
        'message_warning_no_vattype_may_split' => '%1$s You likely have to split the invoice over multiple invoice types. However, please also check your "About your shop" settings.',
        'message_warning_no_vattype_must_split' => '%1$s You have to split the invoice over multiple invoice types.',
        'message_warning_no_vattype_multiple_possible' => '%1$s This is likely due to lack of information about the nature of the items sold. Completing the "About your shop" settings, if possible, may help.',
        'message_warning_line_without_vat' => 'One or more of your invoice lines do not have VAT while you configured that you sell "Only products or services that are VAT liable.". The invoice has been saved as concept. Correct the invoice in Acumulus and check your settings.',
        'message_warning_strategies_failed' => 'Due to an error in your settings or VAT rates, we could not complete all invoice lines correctly. The invoice has been saved as concept. Correct the invoice in Acumulus.',
        'message_warning_missing_amount_added' => 'The invoice total does not match with the lines total. Therefore a corrective line was added with an amount (ex. vat) of €%1$.2f and a vat amount of €%2$.2f. The invoice has been saved as concept. Check and correct the invoice in Acumulus.',
        'message_warning_missing_amount_warn' => 'The invoice total does not match with the lines total. %1$s. The invoice has been saved as concept. Check and correct the invoice in Acumulus.',
        'message_warning_missing_amount_spec' => 'the %1$s differs with €%2$.2f',
        'amount_ex' => 'amount (ex. vat)',
        'amount_inc' => 'amount (inc. vat)',
        'amount_vat' => 'vat amount',

        'message_warning_no_pdf' => 'Because of this warning no invoice PDF has been sent. You will have to do so manually.',
        'message_warning_old_entry_deleted' => 'The invoice has overwritten an older invoice for this %1$s in Acumulus. In Acumulus, this older invoice with entry = %2$d has been moved to the waste bin and can be found under "Views » Latest entries » Deleted entries".',
        'message_warning_old_entry_not_deleted' => 'The invoice has overwritten an older invoice for this %1$s in Acumulus. In Acumulus, we tried to move this older invoice to the waste bin but did not succeed probably because the older invoice has already been deleted. This older invoice with entry = %2$d may or may not still be found under "Views » Latest entries » Deleted entries".',
    );
}
