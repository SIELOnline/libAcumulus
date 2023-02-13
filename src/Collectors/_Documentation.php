<?php
/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpUnused
 */

namespace Siel\Acumulus\Collectors;

/**
 * Documentation for the Collectors namespace
 *
 * The Collectors namespace contains all functionality to get all data needed
 * from a webshop to get an Acumulus invoice.
 *
 * This namespace contains the following "base" classes and interfaces:
 * - {@see CollectorInterface}: Defines a minimal interface for a
 *   {@see Collector} class.
 * - {@see Collector}: An abstract base class that implements
 *   {@see CollectorInterface} and adds some basic code to it. Collectors should
 *   normally extend this class instead of implementing the interface, unless
 *   the strategy as used by this base class does not make sense and is more a
 *   hindrance than a help.
 *
 * Based on the interface and class above, specialized classes exist to collect
 * the different parts of an Acumulus invoice:
 * - {@see CustomerCollector}: Collects data of the customer that placed the
 *   order.
 * - {@see AddressCollector}: Collects an invoice or shipping address.
 * - {@see InvoiceCollector}: Collects the base data of an invoice.
 * - {@see InvoiceLineCollector}: Collects an invoice line.
 * - {@see InvoiceItemLineCollector}: Collects an invoice line for an item
 *   (product or service).
 * - {@see InvoiceFeeLineCollector}: Collects a fee line.
 * - {@see InvoiceShippingFeeLineCollector}: Collects a shipping fee invoice
 *   line.
 * - {@see EmailAsPdfCollector}: Collects data used when mailing an invoice as
 *   pdf.
 *
 * ### Note to developers
 *
 * When implementing a new extension, this is the main namespace for overrides.
 * You should override all the specialized collector classes that are not only
 * pure field mappings, to fill the Acumulus objects with data from your
 * webshop objects, data models, API calls, and metadata.
 */
interface _Documentation
{
}

