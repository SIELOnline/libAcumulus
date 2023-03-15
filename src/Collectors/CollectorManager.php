<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Config\Mappings;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\EmailAsPdf;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;

use function array_key_exists;

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
 * - Gets the mappings from {@see Mappings}, that in turn gets them from
 *   {@see Mapping} and shop defaults (via {@see ShopCapabilities}.
 * - Populates the propertySources parameter.
 * - Executes the Collectors.
 * - Assembles the results (merge child objects into their parent).
 * - Returns the resulting {@see AcumulusObject}.
 */
class CollectorManager
{
    protected FieldExpander $fieldExpander;
    private ShopCapabilities $shopCapabilities;
    private Container $container;
    private Mappings $mappings;
    protected Log $log;

    /** deprecated  Only Source remains with multi-level FieldExpander? */
    protected array $propertySources;

    public function __construct(FieldExpander $fieldExpander, ShopCapabilities $shopCapabilities, Mappings $mappings, Container $container,
        Log $log)
    {
        $this->fieldExpander = $fieldExpander;
        $this->shopCapabilities = $shopCapabilities;
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
        $this->propertySources['invoiceSource'] = $invoiceSource;
        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            $this->propertySources['originalInvoiceSource'] = $invoiceSource->getShopOrder();
        }
        $this->propertySources['source'] = $invoiceSource->getSource();
        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            if ($invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refund'] = $invoiceSource->getSource();
            }
            $this->propertySources['order'] = $invoiceSource->getShopOrder()->getShopSource();
            if ($invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refundedInvoiceSource'] = $invoiceSource->getShopOrder();
                $this->propertySources['refundedOrder'] = $invoiceSource->getShopOrder()->getShopSource();
            }
        }
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
        $invoiceCollector = $this->getContainer()->getCollector('Invoice');
        $invoiceMappings = $this->getMappings()->get(Mappings::Invoice);
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $invoiceCollector->collect(['source' => $source], $invoiceMappings);

        $invoice->setCustomer($this->collectCustomer($source));
        $emailAsPdfSettings = $this->getMappings()->get(Mappings::EmailInvoiceAsPdf);
        if ($emailAsPdfSettings['emailAsPdf']) {
            $invoice->setEmailAsPdf($this->collectEmailAsPdf($source, EmailAsPdfType::Invoice));
        }

        // @todo: invoice lines.

        return $invoice;
    }

    public function collectCustomer(Source $source): Customer
    {
        $customerCollector = $this->getContainer()->getCollector('Customer');
        $customerMappings = $this->getMappings()->get(Mappings::Customer);

        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $customerCollector->collect(['source' => $source], $customerMappings);

        $customer->setInvoiceAddress($this->collectAddress($source, AddressType::Invoice));
        $customer->setShippingAddress($this->collectAddress($source, AddressType::Shipping));

        return $customer;
    }

    public function collectAddress(Source $source, string $addressType): Address
    {
        if ($addressType === AddressType::Invoice) {
            $addressMappings = $this->getMappings()->get(Mappings::InvoiceAddress);
        } else {
            $addressMappings = $this->getMappings()->get(Mappings::ShippingAddress);
        }
        $addressCollector = $this->getContainer()->getCollector('Address');
        /** @var \Siel\Acumulus\Data\Address $address */
        $address = $addressCollector->collect(['source' => $source], $addressMappings);
        return $address;
    }

    public function collectEmailAsPdf(Source $source, string $type): EmailAsPdf
    {
        $emailAsPdfCollector = $this->getContainer()->getCollector('EmailAsPdf');
        if ($type === EmailAsPdfType::Invoice) {
            $emailAsPdfMappings = $this->getMappings()->get(Mappings::EmailInvoiceAsPdf);
        } else {
            $emailAsPdfMappings = $this->getMappings()->get(Mappings::EmailPackingSlipAsPdf);
        }
        $emailAsPdfMappings['emailAsPdfType'] = $type;
        /** @var \Siel\Acumulus\Data\EmailAsPdf $emailAsPdf */
        $emailAsPdf = $emailAsPdfCollector->collect(['source' => $source], $emailAsPdfMappings);
        return $emailAsPdf;
    }
}
