<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

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
 * In keeping webshop specific code as small and easy as possible, we can more
 * easily add support for other webshops, conform to new tax rules, and add new
 * features for all those webshops at once.
 *
 * To construct an Acumulus invoice we have on the input side a number of
 * supported webshops that each have their own way of representing customers,
 * orders, refunds and invoices. Their data should be mapped to the structure of
 * an Acumulus invoice as specified on
 * {@link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/}.
 *
 * This Collector class collects information from the web shop's datamodel. It
 * should do this in a simple way, thus only adding information that is readily
 * available, or at most simple transformations. Thus, if the vat paid is only
 * available as an amount, return that amount, we will not try to calculate the
 * percentage here, we will do that in the generic Completor phase.
 *
 * Information that should be returned can be classified like:
 * - Values that map, more or less directly, to the Acumulus invoice model.
 * - Values that allow to decide how to get certain fields, e.g. whether prices
 *   are entered with vat included or excluded and which address is used for vat
 *   calculations.
 * - Restrict the possible values for certain fields, e.g. the precision of
 *   amounts to limit the range of possible vat percentages.
 * - Validate the resulting Acumulus invoice and raise warnings when possible
 *   errors are detected.
 * - Determine used paths in the code, so we can debug the followed process
 *   when errors are reported.
 *
 * The input of a collection phase is an invoice {@see Source}, typically an
 * order, a refund, or, if supported by the webshop, an invoice from the webshop
 * itself. The output of a collection phase is an
 * {@see \Siel\Acumulus\Invoice\Data} object that contains all necessary
 * data and metadata, so that the subsequent {@see Completor} phase can create a
 * complete and correct Acumulus invoice to send to Acumulus.
 *
 * @todo: how much remains when we finish refactoring this class.
 * This base class:
 * - Implements the basic break down into smaller actions that web shops should
 *   subsequently implement.
 * - Provides helper methods for some recurring functionality.
 * - Documents the expectations of each method to be implemented by a web shop's
 *   Creator class.
 * - Documents the meta tags expected or suggested.
 *
 * A raw invoice:
 * - Contains most invoice tags (as far as they should or can be set), except
 *   'vattype' and 'concept'.
 * - Contains all invoice lines (based on order data), but:
 *     - Possibly hierarchically structured.
 *     - Does not have to be complete or correct.
 *     - In the used currency, not necessarily Euro.
 *
 * @todo: move this to LineCollector?
 * Hierarchically structured invoice lines
 * ---------------------------------------
 * If your shop supports:
 * 1 options or variants, like size, color, etc.
 * 2 bundles or composed products
 * Then you should create hierarchical lines for these product types.
 *
 * ad 1)
 * For each option or variant you add a child line. Set the meta tag
 * 'meta-vatrate-source' to VatRateSource::Parent. Copy the quantity
 * from the parent to the child. Price info is probably on the parent line only,
 * unless your shop administers additional or reduced costs for a given option
 * on the child lines.
 *
 * ad 2)
 * For each product that is part of the bundle add a child line. As this may be
 * a bundle/composed product on its own, you may create multiple levels, there
 * is no maximum depth on child lines.
 *
 * Price info may be on the child lines, but may also be on the parent line,
 * especially so, if the bundle is cheaper that its separate parts. The child
 * lines may have their own vat rates, so depending on your situation fetch the
 * vat info from the child line objects itself or copy it from the parent. When
 * left empty, it is copied from the parent in the Completor phase.
 *
 * Hierarchical lines are "corrected" in the Completor phase, see
 * {@see FlattenerInvoiceLines}
 *
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
    public function collect(PropertySources $propertySources, ?array $fieldSpecifications): AcumulusObject
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
        /** @var \Siel\Acumulus\Data\Customer $customer */
        $customer = $this->getContainer()->getCollector(DataType::Customer)->collect($propertySources, null);
        return $customer;
    }

    protected function collectEmailAsPdf(string $subType, PropertySources $propertySources): EmailInvoiceAsPdf
    {
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $emailAsPdf */
        $emailAsPdf = $this->getContainer()->getCollector(DataType::EmailAsPdf, $subType)->collect($propertySources, null);
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
        $infos = $source->$getInfosMethod();
        if (count($infos) === 0) {
            return;
        }

        /** @var \Siel\Acumulus\Collectors\LineCollector $lineCollector */
        $lineCollector = $this->getContainer()->getCollector(DataType::Line, $lineType);
        $lineMappings = $this->getMappings()->getFor($lineType);
        $propertySourceName = $getInfosMethod;
        if (str_starts_with($propertySourceName, 'get')) {
            $propertySourceName = substr($propertySourceName, strlen('get'));
        }
        if (str_ends_with($propertySourceName, 's')) {
            $propertySourceName = substr($propertySourceName, 0, -strlen('s'));
        }
        $propertySourceName = lcfirst($propertySourceName);
        foreach ($infos as $key => $lineInfo) {
            $propertySources->add($propertySourceName, $lineInfo);
            $propertySources->add('key', $key);
            /** @var \Siel\Acumulus\Data\Line $line */
            $line = $lineCollector->collect($propertySources, $lineMappings);
            if (!$line->metadataGet(Meta::DoNotAdd)) {
                $invoice->addLine($line);
            }
            // Note: item lines should normally not be flattened. However, for other line
            // types we do not expect children, so if there are, it is because the info
            // "object" lead to multiple lines anyway (perhaps for different tax rates).
            if ($flattenChildren) {
                foreach ($line->getChildren() as $child) {
                    $invoice->addLine($child);
                }
                $line->removeChildren();
            }
            $propertySources->remove('key');
            $propertySources->remove($propertySourceName);
        }
    }
}
