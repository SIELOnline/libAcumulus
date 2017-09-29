<?php
namespace Siel\Acumulus\Magento\Magento2\Invoice;

use Siel\Acumulus\Magento\Invoice\Source as BaseSource;
use Siel\Acumulus\Magento\Magento2\Helpers\Registry;

/**
 * Wraps a Magento 2 order or credit memo in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var \Magento\Sales\Model\Order|\Magento\Sales\Model\Order\Creditmemo */
    protected $source;

    /**
     * Loads an Order source for the set id.
     */
    protected function setSourceOrder()
    {
        $this->source = Registry::getInstance()->create('\Magento\Sales\Model\Order');
        /** @noinspection PhpDeprecationInspection http://magento.stackexchange.com/questions/114929/deprecated-save-and-load-methods-in-abstract-model */
        $this->source->load($this->id);
    }

    /**
     * Loads a Credit memo source for the set id.
     */
    protected function setSourceCreditNote()
    {
        $this->source = Registry::getInstance()->create('\Magento\Sales\Model\Order\Creditmemo');
        /** @noinspection PhpDeprecationInspection http://magento.stackexchange.com/questions/114929/deprecated-save-and-load-methods-in-abstract-model */
        $this->source->load($this->id);
    }
}
