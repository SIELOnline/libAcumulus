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

use function count;

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
        $this->collectLinesForType($invoice, LineType::Item, false, 'getItems');
        $this->collectLinesForType($invoice, LineType::Shipping);
        $this->collectLinesForType($invoice, LineType::GiftWrapping);
        $this->collectLinesForType($invoice, LineType::PaymentFee);
        $this->collectLinesForType($invoice, LineType::Other);
        $this->collectLinesForType($invoice, LineType::Discount);
        $this->collectLinesForType($invoice, LineType::Manual);
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
     */
    protected function collectLinesForType(Invoice $invoice, string $lineType, bool $flattenChildren = true, ?string $getInfosMethod = null): void
    {
        /** @var Source $source */
        $source = $this->getPropertySources()->get('source');
        if ($getInfosMethod === null) {
            $getInfosMethod = "get{$lineType}Infos";
        }
        $infos = $source->$getInfosMethod();
        if (count($infos) === 0) {
            return;
        }

        $lineCollector = $this->getContainer()->getCollector(DataType::Line, $lineType);
        $lineMappings = $this->getMappings()->getFor($lineType);
        $propertySourceName = lcfirst($lineType) . 'Info';
        foreach ($infos as $key => $item) {
            $this->getPropertySources()->add($propertySourceName, $item);
            $this->getPropertySources()->add('key', $key);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($this->getPropertySources(), $lineMappings);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
            // Note: item lines should normally not be flattened. However, for other line
            // types we do not expect children, so if there are, it i because the info
            // "object" lead to multiple lines anyway (perhaps for different tax rates).
            if ($flattenChildren) {
                foreach ($line->getChildren() as $child) {
                    $invoice->addLine($child);
                }
                $line->removeChildren();
            }
            $this->getPropertySources()->remove('key');
            $this->getPropertySources()->remove($propertySourceName);
        }
    }
}
