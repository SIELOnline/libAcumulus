<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\Invoice\Completor as BaseCompletor;
use Siel\Acumulus\Meta;

use function count;
use function in_array;

/**
 * Class Completor
 */
class Completor extends BaseCompletor
{
    /**
     * {@inheritdoc}
     *
     * This override checks the:
     * - 'is_vat_exempt' metadata from the "WooCommerce EU vat assistant" plugin
     *   to see if the invoice might be a reversed vat one.
     * - 'is_variable_eu_vat' metadata from the "EU/UK VAT Compliance for
     *   WooCommerce" plugin to make a choice between vat types 6 and 1.
     */
    protected function guessVatType(array $possibleVatTypes): void
    {
        // First try the base guesses,
        parent::guessVatType($possibleVatTypes);
        // and if that did not result in a vat type try the WC specific guesses.
        if (empty($this->invoice->vatType)) {
            $order = $this->source->getOrder()->getShopObject();
            /** @var \WC_Order $order */
            /** @noinspection PhpUndefinedMethodInspection false positive */
            if (in_array(Api::VatType_EuReversed, $possibleVatTypes, true)
                && apply_filters('woocommerce_order_is_vat_exempt', $order->get_meta('is_vat_exempt') === 'yes', $order)) {
                $this->invoice->vatType = Api::VatType_EuReversed;
                $this->invoice->metadataSet(Meta::VatTypeSource,  'WooCommerce\Completor::guessVatType: order is vat exempt');
            }

            if (in_array(Api::VatType_National, $possibleVatTypes, true)
                && in_array(Api::VatType_EuVat, $possibleVatTypes, true)
            ) {
                $vatPaid = $order->get_meta('vat_compliance_vat_paid', true);
                if (!empty($vatPaid)) {
                    $vatPaid = maybe_unserialize($vatPaid);
                    if (isset($vatPaid['by_rates']) && count($vatPaid['by_rates']) === 1) {
                        $vat = reset($vatPaid['by_rates']);
                        if (isset($vat['is_variable_eu_vat'])) {
                            $this->invoice->vatType = $vat['is_variable_eu_vat'] ? Api::VatType_EuVat : Api::VatType_National;
                            $this->invoice->metadataSet(Meta::VatTypeSource, 'WooCommerce\Completor::guessVatType: is_variable_eu_vat');
                        }
                    }
                }
            }
        }
    }
}
