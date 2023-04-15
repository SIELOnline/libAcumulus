<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdf;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;

/**
 * CollectorManager manages the collector phase.
 *
 * Why this CollectorManager?
 * {@see \Siel\Acumulus\Data\AcumulusObject AcumulusObjects} and
 * {@see \Siel\Acumulus\Data\AcumulusProperty AcumulusProperties} are data
 * objects. {@see \Siel\Acumulus\Collectors\Collector Collectors} are the most
 * shop dependent classes and, to facilitate supporting a new shop, should
 * therefore be dumb, dumber, and dumbest. So Collectors should not have to know
 * where mappings and sources come from, they should be passed in and the
 * Collector should do its work: extracting values from the sources and place
 * them into the {@see AcumulusObject} to be returned.
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
    protected Log $log;

    /** deprecated  Only Source remains with multi-level FieldExpander? */
    protected array $propertySources;

    public function __construct(FieldExpander $fieldExpander, Mappings $mappings, Container $container, Log $log)
    {
        $this->fieldExpander = $fieldExpander;
        $this->container = $container;
        $this->mappings = $mappings;
        $this->log = $log;
        $this->propertySources = [];
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    protected function getMappings(): Mappings
    {
        return $this->mappings;
    }

    /**
     * @deprecated  Only Source remains with multi-level FieldExpander?
     */
    protected function getPropertySources(): array
    {
        return $this->propertySources;
    }

    /**
     * Sets the list of sources to search for a property when expanding fields.
     *
     * @deprecated  Only Source remains with multi-level FieldExpander?
     */
    protected function setPropertySources(Source $invoiceSource): void
    {
        $this->propertySources = [];
        $this->propertySources['source'] = $invoiceSource->getSource();
    }

    /**
     * Adds an object as property source.
     *
     * The object is added to the start of the array. Thus, upon token expansion
     * it will be searched before other (already added) property sources.
     * If an object already exists under that name, the existing one will be
     * removed from the array.
     *
     * @param string $name
     *   The name to use for the source
     * @param object|array $property
     *   The source object to add.
     *
     * @deprecated  Only Source remains with multi-level FieldExpander?
     */
    public function addPropertySource(string $name, $property): void
    {
        $this->propertySources = [$name => $property] + $this->propertySources;
    }

    /**
     * Removes an object as property source.
     *
     * @param string $name
     *   The name of the source to remove.
     *
     * @deprecated  Only Source remains with multi-level FieldExpander?
     */
    protected function removePropertySource(string $name): void
    {
        unset($this->propertySources[$name]);
    }

    public function collectInvoice(Source $source): Invoice
    {
        $invoiceCollector = $this->getContainer()->getCollector(DataType::Invoice);
        $invoiceMappings = $this->getMappings()->getFor(DataType::Invoice);
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $invoiceCollector->collect(['source' => $source], $invoiceMappings);

        $invoice->setCustomer($this->collectCustomer($source));
        $invoice->setEmailAsPdf($this->collectEmailAsPdf($source, EmailAsPdfType::Invoice));

        // @todo: invoice lines.

        return $invoice;
    }

    public function collectCustomer(Source $source): Customer
    {
        $customerCollector = $this->getContainer()->getCollector(DataType::Customer);
        $customerMappings = $this->getMappings()->getFor(DataType::Customer);

        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $customerCollector->collect(['source' => $source], $customerMappings);

        $customer->setInvoiceAddress($this->collectAddress($source, AddressType::Invoice));
        $customer->setShippingAddress($this->collectAddress($source, AddressType::Shipping));

        return $customer;
    }

    public function collectAddress(Source $source, string $type): Address
    {
        $addressCollector = $this->getContainer()->getCollector(DataType::Address);
        $addressMappings = $this->getMappings()->getFor($type);
        /** @var \Siel\Acumulus\Data\Address $address */
        $address = $addressCollector->collect(['source' => $source], $addressMappings);
        return $address;
    }

    public function collectEmailAsPdf(Source $source, string $type): EmailAsPdf
    {
        $emailAsPdfCollector = $this->getContainer()->getCollector(DataType::EmailAsPdf);
        $emailAsPdfMappings = $this->getMappings()->getFor($type);
        $emailAsPdfMappings['emailAsPdfType'] = $type;
        /** @var \Siel\Acumulus\Data\EmailAsPdf $emailAsPdf */
        $emailAsPdf = $emailAsPdfCollector->collect(['source' => $source], $emailAsPdfMappings);
        return $emailAsPdf;
    }
}
