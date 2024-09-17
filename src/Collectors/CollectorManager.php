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
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

use function get_class;

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
    private Log $log;

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

    protected function getLog(): Log
    {
        return $this->log;
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
    public function addPropertySource(string $name, object|array $property): CollectorManager
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
    public function collectInvoiceForSource(Source $source, InvoiceAddResult $localResult): Invoice
    {
        return $this->clearPropertySources()
            ->addPropertySource('localResult', $localResult)
            ->setPropertySourcesForSource($source)
            ->collectInvoice();
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

        // @todo: what to do if we have an "empty" address? (see OC examples)
        //   - When to consider an address as being empty?
        //   - Copy all fields or copy only empty fields (the latter seems to contradict
        //     the concept of what an "empty" address constitutes).

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
        $this->addPropertySource('invoice', $invoice);

        /** @var Source $source */
        $source = $this->getPropertySources()['source'];
        $this->collectItemLines($invoice, $source);
        $this->collectShippingLines($invoice, $source);


        // @legacy: Collecting Lines not yet implemented: fall back to the Creator in a
        //   version that is stripped down to these features that have not yet been
        //   converted.
        $creator = $this->getContainer()->getCreator();
        $creator->create($source, $invoice);
        // @legacy end

        $this->removePropertySource('invoice');
    }

    /**
     * Collects all item lines, that is the lines with the products sold.
     */
    private function collectItemLines(Invoice $invoice, Source $source): void
    {
        $lineCollector = $this->getContainer()->getCollector(DataType::Line, LineType::Item);
        $lineMappings = $this->getMappings()->getFor(LineType::Item);

        $items = $source->getItems();
        foreach ($items as $item) {
            $this->addPropertySource('item', $item);
            $product = $item->getProduct();
            if ($product !== null) {
                $this->addPropertySource('product', $product);
            }
            $this->getContainer()->getEvent()->triggerItemLineCollectBefore($item, $this);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
            $this->getContainer()->getEvent()->triggerItemLineCollectAfter($line, $item, $this);
            $invoice->addLine($line);
            $this->removePropertySource('product');
            $this->removePropertySource('item');
        }
    }

    /**
     * Collects all shipping lines.
     *
     * This base implementation covers the case where there is at most 1 shipping line and
     * all needed info can be retrieved using the given $$source.
     *
     * Override this method if:
     * - The shop can have multiple shipping lines.
     * - The shop needs other property sources, so that collecting some of the fields can
     *   be moved from {@see \Siel\Acumulus\Collectors\Collector::collectLogicFields()}
     *   to {@see \Siel\Acumulus\Collectors\Collector::collectMappedFields()}.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     * @param \Siel\Acumulus\Invoice\Source $source
     *
     * @noinspection PhpUnusedParameterInspection  Might be useful in overrides.
     */
    protected function collectShippingLines(Invoice $invoice, Source $source): void
    {
        $this->collectShippingLine($invoice);
    }

    private function collectShippingLine(Invoice $invoice): void
    {
        $lineCollector = $this->getContainer()->getCollector(DataType::Line, LineType::Shipping);
        // If a shop does not have a specialised shipping line collector it means that its
        // Creator code for shipping lines has not yet been converted: do not collect it.
        if (str_contains(get_class($lineCollector), 'Shipping')) {
            $lineMappings = $this->getMappings()->getFor(LineType::Shipping);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
        }
    }
}
