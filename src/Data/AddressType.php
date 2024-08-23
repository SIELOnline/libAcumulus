<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * AddressType defines the possible address types.
 *
 * An address type is used for 2 features:
 * - To define the type of address collected: invoice or shipping.
 * - To define the type of address used for tax calculations: store, invoice, or shipping.
 *
 * NB: We use ...Address to prevent a matching key with the DataType::Invoice constant.
 *
 * @nth: PHP8.1: enumeration.
 */
interface AddressType
{
    public const Shipping = 'ShippingAddress';
    public const Invoice = 'InvoiceAddress';
    // @todo: which shops do support this address type to base vat calculations on? OC, ???.
    // @todo: start using this in the collectors/completors.
    public const Store = 'StoreAddress';
}
