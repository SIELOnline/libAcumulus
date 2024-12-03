<?php
/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpUnused
 */

namespace Siel\Acumulus\Data;

/**
 * Documentation for the Data namespace
 *
 * The Data namespace contains:
 * - The base classes for the data objects that represent Acumulus API messages,
 *   or message parts:
 *   - {@see \Siel\Acumulus\Data\AcumulusObject}.
 *   - {@see \Siel\Acumulus\Data\AcumulusProperty}
 *   - {@see \Siel\Acumulus\Data\MetadataCollection}
 *   - {@see \Siel\Acumulus\Data\MetadataValue}
 * - The data classes that contain all fields (or properties), mostly for the
 *   invoice add call:
 *   - {@see \Siel\Acumulus\Data\Invoice}
 *   - {@see \Siel\Acumulus\Data\Customer}
 *   - {@see \Siel\Acumulus\Data\Address} for the 2 address parts of the
 *     Customer data.
 *   - {@see \Siel\Acumulus\Data\EmailAsPdf} for both sending an invoice (as
 *     part of the invoice add call or as separate call) or packing slip as pdf.
 *   - {@see \Siel\Acumulus\Data\Line} for the invoice lines.
 *
 * All data definitions are represented by a child class of
 * {@see AcumulusObject}. Such a data definition consists of
 * - Scalar values represented by an {@see AcumulusProperty}
 * - Complex values represented by another data definition.
 * - A {@see MetadataCollection} containing {@see MetadataValue}s.
 *
 * Additionally, child classes may contain:
 * - "Convenience" methods, e.g. isCompany() on Customer or isNl() on Address.
 *
 * ### Note to developers
 * When implementing a new extension you should not have to override any of the
 * classes in this namespace.
 */
interface _Documentation
{
}
