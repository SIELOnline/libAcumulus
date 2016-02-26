<?php
namespace Siel\Acumulus\OpenCart\Invoice;

use Siel\Acumulus\Invoice\Source as BaseSource;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Wraps an OpenCart order in an invoice source object.
 */
class Source extends BaseSource
{
    // More specifically typed properties.
    /** @var array */
    protected $source;

    /**
     * {@inheritdoc}
     */
    protected function setSource()
    {
        $this->source = Registry::getInstance()->getOrderModel()->getOrder($this->id);
    }

    /**
     * Sets the id based on the loaded Order.
     */
    protected function setId()
    {
        $this->id = $this->source['order_id'];
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->source['order_status_id'];
    }
}
