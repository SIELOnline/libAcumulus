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
use Throwable;

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
     * @param mixed $property
     *   The source to add, typically an object or an array, but may be a scalar.
     *
     * @return $this
     */
    public function addPropertySource(string $name, mixed $property): CollectorManager
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
        $this->collectShippingLines($invoice);
        $this->collectGiftWrappingLines($invoice);
        $this->collectPaymentFeeLines($invoice);
        $this->collectOtherLines($invoice);
        $this->collectDiscountLines($invoice);
        $this->collectManualLines($invoice);
        $this->collectVoucherLines($invoice);

        $this->removePropertySource('invoice');
    }

    /**
     * Collects all item lines, that is the lines with the products sold.
     *
     * @todo: convert to structure of how other line types are collected, but after event
     *   triggering has been added to that. Note that child handling then should be
     *   adapted for item lines.
 */
    protected function collectItemLines(Invoice $invoice, Source $source): void
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
     * Collects all shipping lines for the current invoice.
     *
     * This base implementation covers the case where there is at most 1 shipping line and
     * all needed info can be retrieved using the given $$source.
     *
     * Override this method if:
     * - The shop can have multiple shipping lines.
     * - The shop needs other property sources, so that collecting some of the fields can
     *   be moved from {@see \Siel\Acumulus\Collectors\Collector::collectLogicFields()}
     *   to {@see \Siel\Acumulus\Collectors\Collector::collectMappedFields()}.
     */
    protected function collectShippingLines(Invoice $invoice): void
    {
        $this->collectLinesForType($invoice, LineType::Shipping);
    }

    /**
     * Collects fee lines for payment fees applied.
     */
    protected function collectGiftWrappingLines(Invoice $invoice): void
    {
        $this->collectLinesForType($invoice, LineType::GiftWrapping);
    }

    /**
     * Collects fee lines for payment fees applied.
     */
    protected function collectPaymentFeeLines(Invoice $invoice): void
    {
        $this->collectLinesForType($invoice, LineType::PaymentFee);
    }

    /**
     * Collects lines for other line types, most likely non-categorised fees.
     */
    protected function collectOtherLines(Invoice $invoice): void
    {
        $this->collectLinesForType($invoice, LineType::Other);
    }

    /**
     * Collects discount lines for all discounts applied.
     */
    protected function collectDiscountLines(Invoice $invoice): void
    {
        $this->collectLinesForType($invoice, LineType::Discount);
    }

    /**
     * Collects manually entered (refund) lines.
     */
    protected function collectManualLines(Invoice $invoice): void
    {
        $this->collectLinesForType($invoice, LineType::Manual);
    }

    /**
     * Collects voucher lines. Voucher are seen as partial payments and will, as such, not
     * have vat.
     */
    protected function collectVoucherLines(Invoice $invoice): void
    {
        $this->collectLinesForType($invoice, LineType::Voucher);
    }

    /**
     * Collects all lines for a given {@see LineType}.
     *
     * This method is not meant to be overridden.
     *
     * @param \Siel\Acumulus\Data\Invoice $invoice
     *   The invoice to add the lines to.
     * @param string $lineType
     *   The type of line to collect. One of the {@see LineType} constants.
     *
     * @todo: add event triggers for all line types, both a general trigger and a line
     *   type specific trigger.
     */
    protected function collectLinesForType(Invoice $invoice, string $lineType): void
    {
        /** @var Source $source */
        $source = $this->getPropertySources()['source'];
        $getInfosMethod = "get{$lineType}Infos";
        $propertySourceName = lcfirst($lineType) . 'Info';

        $lineCollector = $this->getContainer()->getCollector(DataType::Line, $lineType);
        $lineMappings = $this->getMappings()->getFor($lineType);

        $infos = $source->$getInfosMethod();
        foreach ($infos as $key => $item) {
            $this->addPropertySource($propertySourceName, $item);
            $this->addPropertySource('key', $key);
//            $this->getContainer()->getEvent()->triggerLineCollectBefore($lineType, $item, $this);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
//            $this->getContainer()->getEvent()->triggerLineCollectAfter($lineType, $item, $this);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
            // Note: this won't work when we collect Items with this generalised method.
            foreach ($line->getChildren() as $child) {
                $invoice->addLine($child);
            }
            $line->removeChildren();
            $this->removePropertySource($propertySourceName);
        }
    }
}
