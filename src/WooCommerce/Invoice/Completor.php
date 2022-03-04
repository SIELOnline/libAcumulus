<?php
namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Completor as BaseCompletor;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * Class Completor
 */
class Completor extends BaseCompletor
{
    /**
     * {@inheritdoc}
     *
     * This override checks the is_vat_exempt metadata from the WooCommerce EU
     * vat assistant plugin  to see if the invoice might be a reversed vat one.
     */
    protected function guessVatType(array $possibleVatTypes)
    {
        // First try the base guesses,
        parent::guessVatType($possibleVatTypes);
        // and if that did not result in a vat type try the WC specific guesses.
        if (empty($this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType])) {
            /** @var \WC_Order $order */
            $order = $this->source->getOrder()->getSource();
            if (in_array(Api::VatType_EuReversed, $possibleVatTypes)
                && apply_filters('woocommerce_order_is_vat_exempt', $order->get_meta('is_vat_exempt') === 'yes', $order))
            {
                $this->invoice[Tag::Customer][Tag::Invoice][Tag::VatType] = Api::VatType_EuReversed;
                $this->invoice[Tag::Customer][Tag::Invoice][Meta::VatTypeSource] = 'WooCommerce\Completor::guessVatType: order is vat exempt';
            }
        }
    }
}
