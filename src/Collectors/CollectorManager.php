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
use Siel\Acumulus\Data\LineType;
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

    /** deprecated?  Only Source remains with multi-level FieldExpander? */
    private array $propertySources;

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

    protected function getPropertySources(): array
    {
        return $this->propertySources;
    }

    /**
     * Clears the list of sources to search for a property when expanding fields.
     *
     * @return $this
     */
    public function clearPropertySources(): CollectorManager
    {
        $this->propertySources = [];
        return $this;
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
     * @return $this
     */
    public function addPropertySource(string $name, $property): CollectorManager
    {
        $this->propertySources = array_merge($this->propertySources, [$name => $property]);
        return $this;
    }

    /**
     * Removes an object as property source.
     *
     * @param string $name
     *   The name of the source to remove.
     *
     * @return $this
     */
    public function removePropertySource(string $name): CollectorManager
    {
        unset($this->propertySources[$name]);
        return $this;
    }

    /**
     * Sets the property sources for the given {@see \Siel\Acumulus\Invoice\Source}.
     */
    public function setPropertySourcesForSource(Source $source): CollectorManager
    {
        $this->addPropertySource('source', $source);
        return $this;
    }

    /**
     * Collects an invoice for the given {@see \Siel\Acumulus\Invoice\Source}.
     */
    public function collectInvoiceForSource(Source $source): Invoice
    {
        return $this->clearPropertySources()->setPropertySourcesForSource($source)->collectInvoice();
    }

    public function collectInvoice(): Invoice
    {
        $invoiceCollector = $this->getContainer()->getCollector(DataType::Invoice);
        $invoiceMappings = $this->getMappings()->getFor(DataType::Invoice);

        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $invoiceCollector->collect($this->getPropertySources(), $invoiceMappings);

        $invoice->setCustomer($this->collectCustomer());
        $invoice->setEmailAsPdf($this->collectEmailAsPdf(EmailAsPdfType::Invoice));
        $this->collectLines($invoice);

        return $invoice;
    }

    public function collectCustomer(): Customer
    {
        $customerCollector = $this->getContainer()->getCollector(DataType::Customer);
        $customerMappings = $this->getMappings()->getFor(DataType::Customer);

        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $customerCollector->collect($this->getPropertySources(), $customerMappings);

        $customer->setInvoiceAddress($this->collectAddress(AddressType::Invoice));
        $customer->setShippingAddress($this->collectAddress(AddressType::Shipping));

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

    /**
     * Collects the invoice lines.
     */
    private function collectLines(Invoice $invoice): void
    {
        $lineCollector = $this->getContainer()->getCollector(DataType::Line, LineType::Item);
        $lineMappings = $this->getMappings()->getFor(LineType::Item);

        /** @var Source $source */
        $source = $this->getPropertySources()['source'];
        $items = $source->getItems();
        foreach ($items as $item) {
            $this->addPropertySource('item', $item);
            $this->addPropertySource('product', $item->getProduct());
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
            $invoice->addLine($line);
            $this->removePropertySource('product');
            $this->removePropertySource('item');
        }
        // @legacy: Collecting Lines not yet implemented: fall back to the Creator in a
        //   version in the Legacy sub namespace that is stripped down to these features
        //   that has not yet been converted.
        $creator = $this->getContainer()->getCreator();
        $creator->create($source, $invoice);
        // @legacy end
    }
}
