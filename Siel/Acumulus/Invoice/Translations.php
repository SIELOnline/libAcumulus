<?php
namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for classes in the \Siel\Acumulus\Invoice namespace.
 */
class Translations extends TranslationCollection {

  protected $nl = array(
    Source::Order => 'bestelling',
    Source::CreditNote => 'creditnota',
    Source::Other => 'overig',

    'shipping_costs' => 'Verzendkosten',
    'free_shipping' => 'Gratis verzending',
    'payment_costs' => 'Betalingskosten',
    'discount' => 'Korting',
    'discount_code' => 'Kortingscode',
    'coupon_code' => 'Cadeaubon',
    'used' => 'gebruikt',
    'gift_wrapping' => 'Cadeauverpakking',
    'fee' => 'Behandelkosten',
    'refund' => 'Terugbetaling',
    'refund_adjustment' => 'Aanpassing kredietbedrag',

    'message_warning_no_vat' => 'De factuur bevat geen BTW, maar kan niet van het type "verlegde BTW" zijn, noch is de klant buiten de EU gevestigd.',
    'message_warning_incorrect_vat_corrected' => 'De factuur bevat een incorrect BTW tarief van %1$0.1f%%. Dit is gecorrigeerd naar %2$0.1f%%. Controleer deze factuur handmatig in Acumulus!',
    'message_warning_incorrect_vat_not_corrected' => 'De factuur bevat een incorrect BTW tarief van %0.1f%%. Dit viel niet te corrigeren naar een geldig BTW tarief. Corrigeer deze factuur handmatig in Acumulus!',
  );

  protected $en = array(
    Source::Order => 'order',
    Source::CreditNote => 'credit note',
    Source::Other => 'other',

    'shipping_costs' => 'Shipping costs',
    'free_shipping' => 'Free shipping',
    'payment_costs' => 'Payment fee',
    'discount' => 'Discount',
    'discount_code' => 'Coupon code',
    'coupon_code' => 'Voucher',
    'used' => 'used',
    'gift_wrapping' => 'Gift wrapping',
    'fee' => 'Order treatment costs',
    'refund' => 'Refund',
    'refund_adjustment' => 'Refund adjustment',

    'message_warning_no_vat' => 'The invoice has no VAT, but cannot be of the "reversed VAT" type nor is the client located outside the EU.',
    'message_warning_incorrect_vat_corrected' => 'The invoice specified an incorrect VAT rate of %1$0.1f%%. This has been corrected to %2$0.1f%%. Check this invoice in Acumulus!',
    'message_warning_incorrect_vat_not_corrected' => 'The invoice specified an incorrect VAT rate of %0.1f%%. It was not possible to correct this to a valid VAT rate. Correct this invoice manually in Acumulus!',
  );

}
