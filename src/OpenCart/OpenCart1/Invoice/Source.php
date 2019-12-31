<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Invoice;

use Siel\Acumulus\Api;
use Siel\Acumulus\OpenCart\Invoice\Source as BaseSource;

/**
 * OC1 compatibility overrides..
 */
class Source extends BaseSource
{
    /**
     * OC1 does not have a reliable way of detecting if an order is paid or not.
     * Do we just return Api::PaymentStatus_Paid;
     *
     * @return int
     */
    public function getPaymentStatus()
    {
        return Api::PaymentStatus_Paid;
    }
}
