<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * AddressType defines the possible address types.
 *
 * PHP8.1: enumeration.
 */
interface AddressType
{
    public const Shipping = 'shipping';
    public const Invoice = 'invoice';
    // @todo: do we need a constant for store address
    public const Store = 'store';
}
