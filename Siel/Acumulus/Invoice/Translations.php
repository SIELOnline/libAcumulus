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
    // @todo: try to better distinguish free shipping and pickup: for now only WC, HS PS do so
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

    'message_warning_no_vat' => 'De factuur bevat geen BTW en er kan niet bepaald worden welk BTW-type van toepassing is. De factuur is als concept opgeslagen. Corrigeer de factuur in Acumulus en controleer uw BTW instellingen.',
    'message_warning_no_vattype' => 'Door een fout in uw instellingen of BTW tarieven, kan het BTW-type van de factuur niet bepaald worden. De factuur is als concept opgeslagen. Corrigeer of completeer de factuur in Acumulus.',
    'message_warning_multiple_vattype' => 'De factuur kan meerdere BTW-types hebben. De factuur is als concept opgeslagen. Completeer de factuur in Acumulus met het juiste BTW type.',
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

    'message_warning_no_vat' => 'The invoice has no VAT and it is not possible to determine the VAT type. The invoice has been saved as concept. Correct the invoice in acumulus and check your VAT settings.',
    'message_warning_no_vattype' => 'Due to an error in your settings, we cannot determine the VAT type for the invoice. The invoice has been saved as concept. Correct or complete the invoice in Acumulus.',
    'message_warning_multiple_vattype' => 'The invoice can have multiple VAT types. The invoice has been saved as concept. Complete the invoice in Acumulus with the correct VAT type.',
  );

}
