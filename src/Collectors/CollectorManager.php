<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\BasicSubmit;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdf;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;

/**
 * CollectorManager manages the collector phase.
 *
 * Why this CollectorManager?
 * {@see \Siel\Acumulus\Data\AcumulusObject AcumulusObjects} and
 * {@see \Siel\Acumulus\Data\AcumulusProperty AcumulusProperties} are data objects.
 * {@see \Siel\Acumulus\Collectors\Collector Collectors} are the most shop dependent
 * classes and should therefore be as dumb as possible. So Collectors should not have to
 * know where mappings and sources come from, they should be passed in and the Collector
 * should do its work: extracting values from the sources and place them into the
 * {@see AcumulusObject} to be returned.
 *
 * Enter the CollectorManager that, like a controller:
 * - Creates the required {@see Collector Collectors}.
 * - Gets the mappings from {@see Mappings}.
 * - Populates the propertySources parameter.
 * - Executes the Collectors.
 * - Assembles the results (merge child objects into their parent).
 * - Returns the resulting {@see AcumulusObject}.
 */
class CollectorManager
{
    protected FieldExpander $fieldExpander;
    private Container $container;
    private Mappings $mappings;
    private Log $log;
    private PropertySources $propertySources;

    public function __construct(FieldExpander $fieldExpander, Mappings $mappings, Container $container, Log $log)
    {
        $this->fieldExpander = $fieldExpander;
        $this->container = $container;
        $this->mappings = $mappings;
        $this->log = $log;
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getMappings(): Mappings
    {
        return $this->mappings;
    }

    protected function getLog(): Log
    {
        return $this->log;
    }

    public function getPropertySources(): PropertySources
    {
        if (!isset($this->propertySources)) {
            $this->propertySources = $this->getContainer()->createPropertySources();
        }
        return $this->propertySources;
    }

    /**
     * Allows shops to set shop specific property sources besides the already added
     * {@see \Siel\Acumulus\Invoice\Source}.
     */
    public function addShopPropertySources(): void
    {
    }

    /**
     * Collects an invoice for the given {@see \Siel\Acumulus\Invoice\Source}.
     */
    public function collectInvoiceForSource(Source $source, InvoiceAddResult $localResult): Invoice
    {
        $this->getPropertySources()
            ->clear()
            ->add('localResult', $localResult)
            ->add('source', $source);
        $this->addShopPropertySources();
        return $this->collectInvoice();
    }

    public function collectInvoice(): Invoice
    {
        $invoiceMappings = $this->getMappings()->getFor(DataType::Invoice);
        $invoiceCollector = $this->getContainer()->getCollector(DataType::Invoice);
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $invoiceCollector->collect($this->getPropertySources(), $invoiceMappings);
        $this->getPropertySources()->add('invoice', $invoice);

        return $invoice;
    }

    public function collectCustomer(): Customer
    {
        $customerCollector = $this->getContainer()->getCollector(DataType::Customer);
        $customerMappings = $this->getMappings()->getFor(DataType::Customer);

        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $customerCollector->collect($this->getPropertySources(), $customerMappings);
        return $customer;
    }

    /**
     * @param string $subType
     *   One of the {@see AddressType} constants Invoice or Shipping.
     */
    public function collectAddress(string $subType): Address
    {
        $addressCollector = $this->getContainer()->getCollector(DataType::Address, $subType);
        $addressMappings = $this->getMappings()->getFor($subType);

        /** @var \Siel\Acumulus\Data\Address $address */
        $address = $addressCollector->collect($this->getPropertySources(), $addressMappings);
        return $address;
    }

    public function collectEmailAsPdf(string $subType): EmailAsPdf
    {
        $emailAsPdfCollector = $this->getContainer()->getCollector(DataType::EmailAsPdf, $subType);
        $emailAsPdfMappings = $this->getMappings()->getFor($subType);

        /** @var \Siel\Acumulus\Data\EmailAsPdf $emailAsPdf */
        $emailAsPdf = $emailAsPdfCollector->collect($this->getPropertySources(), $emailAsPdfMappings);
        return $emailAsPdf;
    }

    public function collectBasicSubmit(): BasicSubmit
    {
        $this->getPropertySources()
            ->add('config', $this->getContainer()->getConfig())
            ->add('environment', $this->getContainer()->getEnvironment()->get());
        /** @var \Siel\Acumulus\Collectors\BasicSubmitCollector $basicSubmitCollector */
        $basicSubmitCollector = $this->getContainer()->getCollector(DataType::BasicSubmit);
        $basicSubmitMappings = $this->getMappings()->getFor(DataType::BasicSubmit);
        /** @var \Siel\Acumulus\Data\BasicSubmit $basicSubmit */
        $basicSubmit = $basicSubmitCollector->collect($this->getPropertySources(), $basicSubmitMappings);
        $this->getPropertySources()->remove('environment')->remove('config');
        return $basicSubmit;
    }
}
