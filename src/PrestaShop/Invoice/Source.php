<?php
namespace Siel\Acumulus\PrestaShop\Invoice;

use Configuration;
use Context;
use Db;
use Order;
use OrderSlip;
use Siel\Acumulus\Invoice\Source as BaseSource;

/**
 * Wraps a PrestaShop order in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \Order|\OrderSlip */
    protected $source;

    /**
     * {@inheritdoc}
     */
    protected function setSource()
    {
        if ($this->getType() === Source::Order) {
            $this->source = new Order($this->id);
        } else {
            $this->source = new OrderSlip($this->id);
            $this->addProperties();
        }
    }

    /**
     * {@inheritdoc}
     *
     * This override returns the order reference or order slip id.
     */
    public function getReference()
    {
        return $this->getType() === Source::Order
            ? $this->source->reference
            : Configuration::get('PS_CREDIT_SLIP_PREFIX', Context::getContext()->language->id) . sprintf('%06d', $this->source->id);
    }

    /**
     * Sets the id based on the loaded Order.
     */
    protected function setId()
    {
        $this->id = $this->source->id;
        if ($this->getType() === Source::CreditNote) {
            $this->addProperties();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDate()
    {
        return substr($this->source->date_add, 0, strlen('2000-01-01'));
    }

    /**
     * Returns the status of this order.
     *
     * @return int
     */
    protected function getStatusOrder()
    {
        return $this->source->current_state;
    }

    /**
     * Returns the status of this credit note.
     *
     * @return null
     */
    protected function getStatusCreditNote()
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceReferenceOrder()
    {
        return !empty($this->source->invoice_number)
            ? Configuration::get('PS_INVOICE_PREFIX', (int) $this->getSource()->id_lang, null, $this->getSource()->id_shop) . sprintf('%06d', $this->getSource()->invoice_number)
            : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getInvoiceDateOrder()
    {
        return !empty($this->getSource()->invoice_number)
            ? substr($this->getSource()->invoice_date, 0, strlen('2000-01-01'))
            : null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopOrder()
    {
        /** @var \OrderSlip $orderSlip */
        $orderSlip = $this->source;
        return $orderSlip->id_order;
    }

    /**
     * {@inheritdoc}
     */
    protected function getShopCreditNotes()
    {
        /** @var \Order $order */
        $order = $this->source;
        return $order->getOrderSlipsCollection();
    }

    /**
     * OrderSlip does store but not load the values total_products_tax_excl,
     * total_shipping_tax_excl, total_products_tax_incl, and
     * total_shipping_tax_incl. As we need them, we load them ourselves.
     *
     * @throws \PrestaShopDatabaseException
     */
    protected function addProperties()
    {
        $row = Db::getInstance()->executeS(sprintf('SELECT * FROM `%s` WHERE `%s` = %u',
            _DB_PREFIX_ . OrderSlip::$definition['table'], OrderSlip::$definition['primary'], $this->id));
        // Get 1st (and only) result.
        $row = reset($row);
        foreach ($row as $key => $value) {
            if (!isset($this->source->$key)) {
                $this->source->$key = $value;
            }
        }
    }
}
