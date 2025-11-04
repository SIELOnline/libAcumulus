<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $invoice may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Invoice\InvoiceAddResult;

use function assert;

/**
 * CustomerCompletor completes an {@see \Siel\Acumulus\Data\Customer}.
 *
 * After an invoice has been collected, the shop-specific part, it needs to be
 * completed, also the customer part. Think of things like:
 * - Adding customer type based on a setting.
 * - Anonymising data.
 */
class CustomerCompletor extends BaseCompletor
{
    private Customer $customer;

    /**
     * Completes an {@see Customer}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param Customer $acumulusObject
     * @param InvoiceAddResult $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        $this->customer = $acumulusObject;
        assert($result instanceof InvoiceAddResult);

        $this->completeAddresses($result);
        $this->getContainer()->getCompletorTask('Customer', 'Defaults')->complete($this->customer);
        $this->getContainer()->getCompletorTask('Customer', 'ByConfig')->complete($this->customer);
        $this->getContainer()->getCompletorTask('Customer', 'Email')->complete($this->customer);
        $this->getContainer()->getCompletorTask('Customer', 'Anonymise')->complete($this->customer);
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Address}es of the {@see Customer}.
     */
    protected function completeAddresses(MessageCollection $result): void
    {
        $this->getContainer()->getCompletor('Address')->complete($this->customer->getInvoiceAddress(), $result);
        $this->getContainer()->getCompletor('Address')->complete($this->customer->getShippingAddress(), $result);
    }

}
