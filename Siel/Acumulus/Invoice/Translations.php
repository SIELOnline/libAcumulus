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

    'vat' => 'BTW',
    'inc_vat' => 'incl. BTW',
    'ex_vat' => 'excl. BTW',
    'shipping_costs' => 'Verzendkosten',
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

    'message_warning_no_vat' => 'De factuur bevat geen BTW en er kan niet bepaald worden welk BTW-type vam toepassing is.',
    'message_warning_incorrect_vat_corrected' => 'De factuur bevat een incorrect BTW tarief van %1$0.1f%%. Dit is gecorrigeerd naar %2$0.1f%%. Controleer deze factuur handmatig in Acumulus!',
    'message_warning_incorrect_vat_not_corrected' => 'De factuur bevat een incorrect BTW tarief van %0.1f%%. Dit viel niet te corrigeren naar een geldig BTW tarief. Corrigeer deze factuur handmatig in Acumulus!',
  );

  protected $en = array(
    Source::Order => 'order',
    Source::CreditNote => 'credit note',
    Source::Other => 'other',

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

    'message_warning_no_vat' => 'The invoice has no VAT and it is not possible to determine the VAT type.',
    'message_warning_incorrect_vat_corrected' => 'The invoice specified an incorrect VAT rate of %1$0.1f%%. This has been corrected to %2$0.1f%%. Check this invoice in Acumulus!',
    'message_warning_incorrect_vat_not_corrected' => 'The invoice specified an incorrect VAT rate of %0.1f%%. It was not possible to correct this to a valid VAT rate. Correct this invoice manually in Acumulus!',
  );

}
