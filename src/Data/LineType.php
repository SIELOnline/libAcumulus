<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * LineType defines the possible types of invoice lines.
 *
 * PHP8.1: enumeration.
 */
interface LineType
{
    public const Item = 'order-item';
    public const Shipping = 'shipping-line';
    public const PaymentFee = 'payment';
    public const GiftWrapping = 'gift';
    public const Manual = 'manual';
    public const Discount = 'discount';
    public const Voucher = 'voucher';
    public const Other = 'other';
    public const Corrector = 'missing-amount-corrector';
}
