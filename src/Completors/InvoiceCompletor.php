<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\PropertySet;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Tag;

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

    public function complete(Invoice $invoice): void
    {
        $this->invoice = $invoice;

        $this->container->getInvoiceCompletor('InvoiceNumber')->complete($this->invoice, $this->configGet('invoiceNrSource'));
        $this->container->getInvoiceCompletor('IssueDate')->complete($this->invoice, $this->configGet('dateToUse'));
        $this->container->getInvoiceCompletor('AccountingInfo')->complete($this->invoice,
            $this->configGet('defaultCostCenter'),
            $this->configGet('paymentMethodCostCenter'),
            $this->configGet('defaultAccountNumber'),
            $this->configGet('paymentMethodAccountNumber'),
        );

        // As last!
        $this->container->getInvoiceCompletor('Concept')->complete($this->invoice, $this->configGet('concept'));
    }

    private function completeAccountingInfo(): void
    {

    }

}
