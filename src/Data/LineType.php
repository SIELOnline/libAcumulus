<?php

declare(strict_types=1);

namespace Siel\Acumulus\Data;

/**
 * LineType defines the possible types of invoice lines.
 *
 * Line type are used to define the type of line collected.
 *
 * @nth: PHP8.1: enumeration.
 */
interface LineType
{
    public const Item = 'ItemLine';
    public const Shipping = 'ShippingLine';
    public const GiftWrapping = 'GiftWrappingFeeLine';
    public const PaymentFee = 'PaymentFeeLine';
    public const Other = 'OtherLine';
    public const Discount = 'DiscountLine';
    public const Manual = 'ManualLine';
    public const Voucher = 'VoucherLine';
    public const Corrector = 'MissingAmountCorrectorLine';
}
