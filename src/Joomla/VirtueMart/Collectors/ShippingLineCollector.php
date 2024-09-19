<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;
use VmModel;

/**
 * ShippingLineCollector contains VirtueMart specific {@see LineType::Shipping} collecting logic.
 *
 * @noinspection PhpUnused  Instantiated via a factory.
 */
class ShippingLineCollector extends LineCollector
{
    /**
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     *   A shipping line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectLogicFields(AcumulusObject $acumulusObject): void
    {
        $this->collectShippingLine($acumulusObject);
    }

    /**
     * Collects the shipping line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A shipping line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectShippingLine(Line $line): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();

        // We are checking on empty, assuming that a null value will be used to
        // indicate no shipping at all (downloadable product) and that free
        // shipping will be represented as the string '0.00' which is not
        // considered empty.
        if (!empty($order['details']['BT']->order_shipment)) {
            $shippingEx = (float) $order['details']['BT']->order_shipment;
            $shippingVat = (float) $order['details']['BT']->order_shipment_tax;
            $line->product = $this->getShippingMethodName();
            $line->unitPrice = $shippingEx;
            $line->quantity = 1;
            $line->metadataSet(Meta::VatAmount, $shippingVat);
            $this->addVatData($line, 'shipment', $shippingEx, $shippingVat);
        }
    }

    protected function getShippingMethodName(): string
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $this->getPropertySource('source');
        $order = $source->getShopObject();

        /** @var \VirtueMartModelShipmentmethod $shipmentMethodModel */
        $shipmentMethodModel = VmModel::getModel('shipmentmethod');
        /** @var \TableShipmentmethods $shipmentMethod */
        $shipmentMethod = $shipmentMethodModel->getShipment($order['details']['BT']->virtuemart_shipmentmethod_id);
        if (!empty($shipmentMethod->shipment_name)) {
            return $shipmentMethod->shipment_name;
        }
        return parent::getShippingMethodName();
    }
}
