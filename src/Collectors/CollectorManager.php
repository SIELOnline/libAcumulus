<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Data\Address;
use Siel\Acumulus\Data\BasicSubmit;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdf;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\FieldExpander;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Product\Product;
use Siel\Acumulus\Product\StockTransactionResult;

/**
 * CollectorManager manages the collector phase.
 *
 * Why this CollectorManager?
 * {@see \Siel\Acumulus\Data\AcumulusObject AcumulusObjects} and
 * {@see \Siel\Acumulus\Data\AcumulusProperty AcumulusProperties} are data objects and do
 * not contain complex or shop dependent logic.
 * {@see \Siel\Acumulus\Collectors\Collector Collectors} are the most shop dependent
 * classes that need mappings and sources coming from shop, environment, and config.
 * Other code that needs an AcumulusObject should not have to know where to get all this
 * data from, except for its local object it is working on (e.g. a
 * {@see \Siel\Acumulus\Invoice\Source} or {@see \Siel\Acumulus\Invoice\Item}).
 * Enter the CollectorManager that, like a controller:
 * - Creates the required {@see Collector Collectors}.
 * - Populates the propertySources parameter.
 * - Executes the Collector.
 * - Returns the resulting {@see AcumulusObject}.
 */
class CollectorManager
{
    protected FieldExpander $fieldExpander;
    private Container $container;
    private Log $log;
    private PropertySources $propertySources;

    public function __construct(FieldExpander $fieldExpander, Container $container, Log $log)
    {
        $this->fieldExpander = $fieldExpander;
        $this->container = $container;
        $this->log = $log;
    }

    protected function getContainer(): Container
    {
        return $this->container;
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
        /** @var \Siel\Acumulus\Collectors\InvoiceCollector $invoiceCollector */
        $invoiceCollector = $this->getContainer()->getCollector(DataType::Invoice);
        return $invoiceCollector->collect($this->getPropertySources());
    }

    public function collectCustomer(): Customer
    {
        /** @var \Siel\Acumulus\Collectors\CustomerCollector $customerCollector */
        $customerCollector = $this->getContainer()->getCollector(DataType::Customer);
        return $customerCollector->collect($this->getPropertySources());
    }

    /**
     * @param string $subType
     *   One of the {@see AddressType} constants Invoice or Shipping.
     */
    public function collectAddress(string $subType): Address
    {
        /** @var \Siel\Acumulus\Collectors\AddressCollector $addressCollector */
        $addressCollector = $this->getContainer()->getCollector(DataType::Address, $subType);
        return $addressCollector->collect($this->getPropertySources());
    }

    public function collectEmailAsPdf(string $subType): EmailAsPdf
    {
        /** @var \Siel\Acumulus\Collectors\EmailAsPdfCollector $emailAsPdfCollector */
        $emailAsPdfCollector = $this->getContainer()->getCollector(DataType::EmailAsPdf, $subType);
        return $emailAsPdfCollector->collect($this->getPropertySources());
    }

    /**
     * Collects a stock transaction for the given {@see \Siel\Acumulus\Product\Product} and change.
     */
    public function collectStockTransactionForItemLine(Product $product, int|float $change, ?Item $item, StockTransactionResult $localResult): StockTransaction
    {
        $this->getPropertySources()
            ->clear()
            ->add('localResult', $localResult)
            ->add('product', $product)
            ->add('change', $change)
            ->add('item', $item)
            ->add('environment', $this->getContainer()->getEnvironment()->toArray());
        $this->addShopPropertySources();
        return $this->collectStockTransaction();
    }

    public function collectStockTransaction(): StockTransaction
    {
        /** @var \Siel\Acumulus\Collectors\StockTransactionCollector $stockTransactionCollector */
        $stockTransactionCollector = $this->getContainer()->getCollector(DataType::StockTransaction);
        return $stockTransactionCollector->collect($this->getPropertySources());
    }

    public function collectBasicSubmit(): BasicSubmit
    {
        $this->getPropertySources()
            ->add('config', $this->getContainer()->getConfig())
            ->add('environment', $this->getContainer()->getEnvironment()->toArray());
        /** @var \Siel\Acumulus\Collectors\BasicSubmitCollector $basicSubmitCollector */
        $basicSubmitCollector = $this->getContainer()->getCollector(DataType::BasicSubmit);
        $basicSubmit = $basicSubmitCollector->collect($this->getPropertySources());
        $this->getPropertySources()->remove('environment')->remove('config');
        return $basicSubmit;
    }
}
