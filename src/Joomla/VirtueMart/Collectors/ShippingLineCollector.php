<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\VirtueMart\Collectors;

use Siel\Acumulus\Collectors\PropertySources;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Meta;
use VmModel;

/**
 * ShippingLineCollector contains VirtueMart specific {@see LineType::Shipping} collecting
 * logic.
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
    protected function collectLogicFields(AcumulusObject $acumulusObject, PropertySources $propertySources): void
    {
        $this->collectShippingLine($acumulusObject, $propertySources);
    }

    /**
     * Collects the shipping line for the invoice.
     *
     * @param \Siel\Acumulus\Data\Line $line
     *   A shipping line with the mapped fields filled in.
     *
     * @throws \Exception
     */
    protected function collectShippingLine(Line $line, PropertySources $propertySources): void
    {
        // Set some often used variables.
        /** @var \Siel\Acumulus\Invoice\Source $source */
        $source = $propertySources->get('source');
        $order = $source->getShopObject();

        $shippingEx = (float) $order['details']['BT']->order_shipment;
        $shippingVat = (float) $order['details']['BT']->order_shipment_tax;
        $line->product = $this->getShippingMethodName((int) $order['details']['BT']->virtuemart_shipmentmethod_id);
        $line->unitPrice = $shippingEx;
        $line->quantity = 1;
        $line->metadataSet(Meta::VatAmount, $shippingVat);
        $this->addVatData($line, 'shipment', $shippingVat);
    }

    protected function getShippingMethodName(mixed ...$args): string
    {
        [$shipmentMethodId] = $args;
        /** @var \VirtueMartModelShipmentmethod $shipmentMethodModel */
        $shipmentMethodModel = VmModel::getModel('shipmentmethod');
        /** @var \TableShipmentmethods $shipmentMethod */
        $shipmentMethod = $shipmentMethodModel->getShipment($shipmentMethodId);
        if (!empty($shipmentMethod->shipment_name)) {
            return $shipmentMethod->shipment_name;
        }
        return parent::getShippingMethodName();
    }
}
