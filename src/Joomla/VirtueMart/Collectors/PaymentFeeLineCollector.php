<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

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
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectPaymentFeeLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the payment fee line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A payment fee line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectPaymentFeeLine(Line $line, PropertySources $propertySources): void
    {
        /** @var Source $source */
        $source = $propertySources->get('source');

        $paymentEx = (float) $source->getShopObject()['details']['BT']->order_payment;
        $paymentVat = (float) $source->getShopObject()['details']['BT']->order_payment_tax;
        $line->product = $this->t('payment_costs');
        $line->unitPrice = $paymentEx;
        $line->quantity = 1;
        $line->metadataSet(Meta::VatAmount, $paymentVat);
        $this->addVatData($line, 'payment', $paymentVat);
    }
}

