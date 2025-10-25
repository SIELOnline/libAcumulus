<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use ArrayObject;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailAsPdfType;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Meta;

use function count;
use function strlen;

/**
 * Collects information to construct an Acumulus invoice.
 *
 * Properties that are mapped:
 * - string $description
 * - string $descriptionText
 * - string $invoiceNotes
 *
 * Properties that are computed using logic (the logic may be put in a method
 * of {@see Source}, making it a mapping for the Collector):
 * - int $paymentStatus (typically based on recorded order status history).
 * - \DateTimeInterface $paymentDate (typically based on recorded order status history).
 *
 * Properties that are based on configuration and, optionally, metadata and
 * Completor findings, and thus are not set here:
 * - int $concept
 * - string $conceptType (no clue how to set this)
 * - int $number (metadata regarding order and, if available, invoice number
 *   will be added)
 * - int $vatType
 * - \DateTimeInterface $issueDate (metadata regarding order and, if available, invoice
 *   date will be added)
 * - int $costCenter (Completor phase: based on config and metadata about
 *   payment method)
 * - int $accountNumber (Completor phase: based on config and metadata about
 *   payment method)
 * - int $template
 *
 * In keeping webshop-specific code as small and easy as possible, we can more
 * easily add support for other webshops, conform to new tax rules, and add new
 * features for all those webshops at once.
 *
 * To construct an "Acumulus invoice", we have on the input side a number of
 * supported webshops that each have their own way of representing customers,
 * orders, refunds and invoices. Their data should be mapped to the structure of
 * an Acumulus invoice as specified on
 * {@link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/}.
 *
 * This Collector class collects information from the web shop's datamodel. It
 * should do this in a simple way, thus only adding information that is readily
 * available, or at most simple transformations. Thus, if the vat paid is only
 * available as an amount, return that amount. We will not try to calculate the
 * percentage here, we will do that in the common Completor phase.
 *
 * Information that should be returned can be classified like:
 * - Values that map, more or less directly, to the Acumulus invoice model.
 * - Values that allow deciding how to get certain fields, e.g. whether prices
 *   are entered with vat included or excluded and which address is used for vat
 *   calculations.
 * - Restrict the possible values for certain fields, e.g. the precision of
 *   amounts to limit the range of possible vat percentages.
 * - Validate the resulting Acumulus invoice and raise warnings when possible
 *   errors are detected.
 * - Determine used paths in the code, so we can debug the process when errors are
 *   reported.
 *
 * The input of a collection phase is an invoice {@see Source}, typically an order, a
 * refund, or, if supported by the webshop, an invoice from the webshop itself. The output
 * of a collection phase is an {@see \Siel\Acumulus\Data\Invocie} object that contains all
 * necessary data and metadata, so that the subsequent
 * {@see \Siel\Acumulus\Completors\InvoiceCompletor} phase can complete and correct the
 * Acumulus invoice to send to Acumulus.
 *
 * This class:
 * - Collects fields and metadata for the main invoice part.
 * - Calls other collectors to collect other parts of the invoice: customer, addresses,
 *   lines and emailAsPdf
 *
 * A raw invoice:
 * - Contains most invoice tags (as far as they should or can be set), except
 *   'vattype' and 'concept'.
 * - Contains all invoice lines (based on order data), but:
 *     - Possibly hierarchically structured.
 *     - Does not have to be complete or correct.
 *     - In the used currency, so not necessarily Euro.
 */
class InvoiceCollector extends Collector
{
    /**
     * This override collects the fields of an {@see \Siel\Acumulus\Data\Invoice} object,
     * as well as of its child properties: {@see \Siel\Acumulus\Data\Customer},
     * {@see \Siel\Acumulus\Data\EmailInvoiceAsPdf} and all its
     * {@see \Siel\Acumulus\Data\Line}s.
     *
     * @return \Siel\Acumulus\Data\Invoice
     */
    public function collect(PropertySources $propertySources, ?ArrayObject $fieldSpecifications = null): AcumulusObject
    {
        /** @var Invoice $invoice */
        $invoice = parent::collect($propertySources, $fieldSpecifications);

        $propertySources->add('invoice', $invoice);
        $invoice->setCustomer($this->collectCustomer($propertySources));
        $invoice->setEmailAsPdf($this->collectEmailAsPdf(EmailAsPdfType::Invoice, $propertySources));
        $this->collectLines($invoice, $propertySources);

        return $invoice;
    }

    protected function collectCustomer(PropertySources $propertySources): Customer
    {
        /** @var \Siel\Acumulus\Collectors\CustomerCollector $customerCollector */
        $customerCollector = $this->getContainer()->getCollector(DataType::Customer);
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $customerCollector->collect($propertySources);
        return $customer;
    }

    protected function collectEmailAsPdf(string $subType, PropertySources $propertySources): EmailInvoiceAsPdf
    {
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $emailAsPdf */
        $emailAsPdf = $this->getContainer()->getCollector(DataType::EmailAsPdf, $subType)->collect($propertySources);
        return $emailAsPdf;
    }

    /**
     * Collects the invoice lines.
     */
    protected function collectLines(Invoice $invoice, PropertySources $propertySources): void
    {
        $this->collectLinesForType($invoice, LineType::Item, $propertySources, false, 'getItems');
        $this->collectLinesForType($invoice, LineType::Shipping, $propertySources);
        $this->collectLinesForType($invoice, LineType::GiftWrapping, $propertySources);
        $this->collectLinesForType($invoice, LineType::PaymentFee, $propertySources);
        $this->collectLinesForType($invoice, LineType::Other, $propertySources);
        $this->collectLinesForType($invoice, LineType::Discount, $propertySources);
        $this->collectLinesForType($invoice, LineType::Manual, $propertySources);
        $this->collectLinesForType($invoice, LineType::Voucher, $propertySources);
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
    protected function collectLinesForType(Invoice $invoice, string $lineType, PropertySources $propertySources, bool $flattenChildren = true, ?string $getInfosMethod = null): void
    {
        /** @var Source $source */
        $source = $propertySources->get('source');
        if ($getInfosMethod === null) {
            $getInfosMethod = "get{$lineType}Infos";
        }
        // Retrieve info objects: we will create 1 line per object.
        $infos = $source->$getInfosMethod();
        if (count($infos) === 0) {
            // No info objet => no lines to create: return early, because otherwise we
            // might try to instantiate an abstract Collector.
            return;
        }

        /** @var \Siel\Acumulus\Collectors\LineCollector $lineCollector */
        $lineCollector = $this->getContainer()->getCollector(DataType::Line, $lineType);
        $lineMappings = $this->getMappings()->getFor($lineType);
        // Will result in something like shippingInfo, paymentFeeInfo, etc. OR just 'item'
        // for item lines.
        $lineInfoName = $getInfosMethod;
        if (str_starts_with($lineInfoName, 'get')) {
            $lineInfoName = substr($lineInfoName, strlen('get'));
        }
        if (str_ends_with($lineInfoName, 's')) {
            $lineInfoName = substr($lineInfoName, 0, -strlen('s'));
        }
        $lineInfoName = lcfirst($lineInfoName);
        foreach ($infos as $key => $lineInfo) {
            $propertySources->add($lineInfoName, $lineInfo);
            $propertySources->add('key', $key);
            $line = $lineCollector->collect($propertySources, $lineMappings);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
            // Note: item lines should normally not be flattened. However, for other line
            // types we do not expect children, so if there are, it is because the info
            // "object" leads to multiple lines anyway (perhaps for different tax rates).
            if ($flattenChildren) {
                foreach ($line->getChildren() as $child) {
                    $invoice->addLine($child);
                }
                $line->removeChildren();
            }
            $propertySources->remove('key');
            $propertySources->remove($lineInfoName);
        }
    }
}
