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
    public const Item = 'OrderItem';
    public const Shipping = 'Shipping';
    public const PaymentFee = 'Payment';
    public const GiftWrapping = 'Gift';
    public const Manual = 'Manual';
    public const Discount = 'Discount';
    public const Voucher = 'Voucher';
    public const Other = 'Other';
    public const Corrector = 'MissingAmountCorrector';
}
