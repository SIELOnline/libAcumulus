<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\AddressType;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\EmailAsPdf;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Field;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\Source;

use function array_key_exists;

/**
 * CollectorManager manages the collector phase.
 *
 * {@see \Siel\Acumulus\Data\AcumulusObject AcumulusObjects} and
 * {@see \Siel\Acumulus\Data\AcumulusProperty AcumulusProperties} are data
 * objects. {@see \Siel\Acumulus\Collectors\Collector Collectors} are the most
 * shop dependent classes and, to facilitate supporting a new shop, should
 * therefore be dumb, dumber, dumbest.
 *
 * Enter the CollectorManager that, like a controller, creates the needed
 * {@see Collector Collectors}, executes them by providing the needed mappings,
 * and connects the various resulting
 * {@see \Siel\Acumulus\Data\AcumulusObject AcumulusObjects}.
 */
class CollectorManager
{
    protected Field $field;
    private ShopCapabilities $shopCapabilities;
    private Container $container;
    private Config $config;
    protected Log $log;
    protected array $propertySources;

    public function __construct(Field $field, ShopCapabilities $shopCapabilities, Config $config, Container $container, Log $log)
    {
        $this->field = $field;
        $this->shopCapabilities = $shopCapabilities;
        $this->container = $container;
        $this->config = $config;
        $this->log = $log;
        $this->propertySources = [];
    }

    protected function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * @return \Siel\Acumulus\Config\Config
     */
    protected function getConfig(): Config
    {
        return $this->config;
    }


    /**
     * @return array
     */
    protected function getPropertySources(): array
    {
        return $this->propertySources;
    }

    /**
     * Sets the list of sources to search for a property when expanding fields.
     */
    protected function setPropertySources(Source $invoiceSource): void
    {
        $this->propertySources = [];
        $this->propertySources['invoiceSource'] = $invoiceSource;
        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            $this->propertySources['originalInvoiceSource'] = $invoiceSource->getOrder();
        }
        $this->propertySources['source'] = $invoiceSource->getSource();
        if (array_key_exists(Source::CreditNote, $this->shopCapabilities->getSupportedInvoiceSourceTypes())) {
            if ($invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refund'] = $invoiceSource->getSource();
            }
            $this->propertySources['order'] = $invoiceSource->getOrder()->getSource();
            if ($invoiceSource->getType() === Source::CreditNote) {
                $this->propertySources['refundedInvoiceSource'] = $invoiceSource->getOrder();
                $this->propertySources['refundedOrder'] = $invoiceSource->getOrder()->getSource();
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
     */
    protected function removePropertySource(string $name): void
    {
        unset($this->propertySources[$name]);
    }

    public function collectInvoice(): Invoice
    {
        $invoiceCollector = $this->getContainer()->getCollector('Invoice');
        $invoiceMappings = $this->getConfig()->getInvoiceSettings();
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $invoiceCollector->collect($this->getPropertySources(), $invoiceMappings);

        $invoice->setCustomer($this->collectCustomer());
        $emailAsPdfSettings = $this->getConfig()->getEmailAsPdfSettings();
        if ($emailAsPdfSettings['emailAsPdf']) {
            $invoice->setEmailAsPdf($this->collectEmailAsPdf(EmailAsPdfType::Invoice));
        }

        // @todo: invoice lines.

        return $invoice;
    }

    public function collectCustomer(): Customer
    {
        $customerCollector = $this->getContainer()->getCollector('Customer');
        $customerMappings = $this->getConfig()->getCustomerSettings();
        $customerMappings['type'] = $customerMappings['defaultCustomerType'];
        unset($customerMappings['defaultCustomerType']);
        $customerMappings['telephone2'] = $this->getConfig()->get('telephone2') ;
        $customerMappings['disableDuplicates'] = $this->getConfig()->get('disableDuplicates') ;

        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $customerCollector->collect($this->getPropertySources(), $customerMappings);

        $customer->setInvoiceAddress($this->collectAddress(AddressType::Invoice));
        $customer->setShippingAddress($this->collectAddress(AddressType::Shipping));

        return $customer;
    }

    public function collectAddress(string $addressType): Address
    {
        if ($addressType === AddressType::Invoice) {
            $addressMappings = $this->getConfig()->getInvoiceAddressSettings();
        } else {
            $addressMappings = $this->getConfig()->getShippingAddressSettings();
        }
        $addressCollector = $this->getContainer()->getCollector('Address');
        /** @var \Siel\Acumulus\Data\Address $address */
        $address = $addressCollector->collect($this->getPropertySources(), $addressMappings);
        return $address;
    }

    public function collectEmailAsPdf(string $type): EmailAsPdf
    {
        $emailAsPdfCollector = $this->getContainer()->getCollector('EmailAsPdf');
        if ($type === EmailAsPdfType::Invoice) {
            $emailAsPdfMappings = $this->getConfig()->getInvoiceEmailAsPdfSettings();
        } else {
            $emailAsPdfMappings = $this->getConfig()->getPackingSlipEmailAsPdfSettings();
        }
        $emailAsPdfMappings['emailAsPdfType'] = $type;
        /** @var \Siel\Acumulus\Data\EmailAsPdf $emailAsPdf */
        $emailAsPdf = $emailAsPdfCollector->collect($this->getPropertySources(), $emailAsPdfMappings);
        return $emailAsPdf;
    }
}
