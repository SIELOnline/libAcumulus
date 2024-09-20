<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tag;

/**
 * PaymentFeeLineCollector contains VirtueMart specific {@see LineType::PaymentFee}
 * collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class PaymentFeeLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectPaymentFeeLine($acumulusObject);
    }

    /**
     * Collects the payment fee line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectPaymentFeeLine(Line $line): void
    {
        /**
         * @var Source $source
         *   Either a source (coupon) or a calc_rule record in a stdClass
         */
        $source = $this->getPropertySource('source');

        $paymentEx = (float) $source->getShopObject()['details']['BT']->order_payment;
        if (!Number::isZero($paymentEx)) {
            $paymentVat = (float) $source->getShopObject()['details']['BT']->order_payment_tax;
            $line->product = $this->t('payment_costs');
            $line->unitPrice = $paymentEx;
            $line->quantity = 1;
            $line->metadataSet(Meta::VatAmount, $paymentVat);
            $this->addVatData($line, 'payment', $paymentEx, $paymentVat);
        } else {
            $line->metadataSet(Meta::DoNotAdd, true);
        }
    }
}

