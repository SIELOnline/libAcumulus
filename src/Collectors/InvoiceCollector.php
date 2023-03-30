<?php

declare(strict_types=1);

namespace Siel\Acumulus\Collectors;

use Siel\Acumulus\Invoice\Source;

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
 * - \DateTime $paymentDate (typically based on recorded order status history).
 *
 * Properties that are based on configuration and, optionally, metadata and
 * Completor findings, and thus are not set here:
 * - int $concept
 * - string $conceptType (no clue how to set this)
 * - int $number (metadata regarding order and, if available, invoice number
 *   will be added)
 * - int $vatType
 * - \DateTime $issueDate (metadata regarding order and, if available, invoice
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
 * 'meta-vatrate-source' to Creator::VatRateSource_Parent. Copy the quantity
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
}
