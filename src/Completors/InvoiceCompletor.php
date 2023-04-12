<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $invoice may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;

/**
 * InvoiceCompletor completes an {@see \Siel\Acumulus\Data\Invoice}.
 *
 * After an invoice has been collected, the shop specific part, it needs to be
 * completed. think of things like:
 * - Getting vat rates when we have a vat amount and an amount (inc. or ex.).
 * - Determining the cost center, account number and template based on settings
 *   and payment method and status.
 * - Deriving the vat type.
 */
class InvoiceCompletor
{
    private Container $container;
    private Config $config;
    private Invoice $invoice;

    /**
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\Config\Config $config
     */
    public function __construct(Container $container, Config $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * Returns the configured value for this setting.
     *
     * @return mixed|null
     *   The configured value for this setting, or null if not set and no
     *   default is available.
     */
    protected function configGet(string $key)
    {
        return $this->config->get($key);
    }

    /**
     * Completes an {@see \Siel\Acumulus\Data\Invoice}.
     *
     * This phase is executed after the collecting phase.
     */
    public function complete(Invoice $invoice): void
    {
        $this->invoice = $invoice;

        $this->completeCustomer();
        $this->container->getCompletorTask('Invoice', 'InvoiceNumber')->complete($this->invoice);
        $this->container->getCompletorTask('Invoice', 'IssueDate')->complete($this->invoice);
        $this->container->getCompletorTask('Invoice', 'AccountingInfo')->complete($this->invoice);
        $this->container->getCompletorTask('Invoice', 'MultiLineProperties')->complete($this->invoice);
        $this->container->getCompletorTask('Invoice', 'Template')->complete($this->invoice);

        // As last!
        $this->container->getCompletorTask('Invoice', 'Concept')->complete($this->invoice);
    }

    /**
     * Completes the {@see \Siel\Acumulus\Data\Customer} part of the
     * {@see \Siel\Acumulus\Data\Invoice}.
     */
    protected function completeCustomer(): void
    {
        $this->container->getCompletorTask('Customer', 'Anonymise')->complete($this->invoice->getCustomer());
    }
}
