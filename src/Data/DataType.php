<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * AddressType defines the possible address types.
 *
 * PHP8.1: enumeration.
 */
interface DataType
{
    public const Invoice = 'invoice';
    public const Customer = 'customer';
}
