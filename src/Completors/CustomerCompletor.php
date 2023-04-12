<?php
/**
 * @noinspection PhpPrivateFieldCanBeLocalVariableInspection  In the future,
 *   $invoice may be made a local variable, but probably we will need it as a
 *   property.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;

/**
 * CustomerCompletor completes an {@see \Siel\Acumulus\Data\Customer}.
 *
 * After an invoice has been collected, the shop specific part, it needs to be
 * completed, also the customer part. Think of things like:
 * - Adding customer type based on a setting.
 * - Anonymising data.
 */
class CustomerCompletor
{
    private Container $container;
    private Config $config;
    private Customer $customer;

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
     * Completes an {@see \Siel\Acumulus\Data\Customer}.
     *
     * This phase is executed after the collecting phase.
     */
    public function complete(Customer $customer): void
    {
        $this->customer = $customer;
        $this->container->getCompletorTask('Customer', 'ByConfig')->complete($this->customer, $this->configGet('sendCustomer'));
        $this->container->getCompletorTask('Customer', 'Email')->complete($this->customer, $this->configGet('sendCustomer'));
        $this->container->getCompletorTask('Customer', 'Anonymise')->complete($this->customer, $this->configGet('sendCustomer'));
    }
}
