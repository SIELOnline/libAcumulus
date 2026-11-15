<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Invoice;

use RuntimeException;
use Siel\Acumulus\Invoice\Item as BaseItem;
use Siel\Acumulus\Product\Product;

/**
 * Item is the PrestaShop specific class to wrap an order/refund item.
 *
 * A PS Item will have entries from several joined tables.
 *
 * Tables joined for a source with source type = Source::Order:
 *
 * Order detail:
 * - {@see OrderDetail::$definition}
 * - NOTE: 'tax_name' and 'tax+rate' are deprecated. DO NOT DEPEND ON IT.
 * - NOTE: A lot of product information is copied to an order detail, giving historically
 *   correct product details. These are the fields that start with 'product_'.
 *
 * Order detail_tax, the amounts include any discounts applied on the product:
 * - id_order_detail    int
 * - id_tax    int
 * - unit_amount    decimal(16,6) [0.000000] (= vat amount per unit)
 * - total_amount    decimal(16,6) [0.000000] (= total vat amount for the line)
 *
 * Tax:
 * - id_tax    int unsigned Auto nummering
 * - rate    decimal(10,3)
 * - active    tinyint unsigned [1]
 * - deleted    tinyint unsigned [0]
 *
 * Tables joined for a source with source type = Source::CreditNote:
 *
 * Order slip detail:
 * - NOTE: these are only available for a credit note.
 * - id_order_slip    int unsigned
 * - id_order_detail    int unsigned
 * - product_quantity    int unsigned [0]
 * - unit_price_tax_excl    decimal(20,6) NULL
 * - unit_price_tax_incl    decimal(20,6) NULL
 * - total_price_tax_excl    decimal(20,6) NULL
 * - total_price_tax_incl    decimal(20,6) NULL
 * - amount_tax_excl    decimal(20,6) NULL
 * - amount_tax_incl    decimal(20,6) NULL
 *
 * Plus the above tables.
 *
 * NOTE: Before PS1.6.1.1, the order_detail_taxes were not filled, thus the inner joins as
 * PS uses to get the above specified fields will fail and we will have no information at
 * all. We don't handle this anymore and expect all this info to be available.
 *
 * @property array $shopObject Combination of "order detail" and "order detail taxes".
 * @method array getShopObject()
 * @method null getProduct()
 */
class Item extends BaseItem
{
    protected function setShopObject(): void
    {
        throw new RuntimeException('This method is not expected to be called in PrestaShop');
    }

    protected function setId(): void
    {
        $this->id = (int) $this->shopObject['id_order_detail'];
    }

    /**
     * This PrestaShop override returns null as PrestaShop:
     * - stores all relevant product data in the item (an OrderDetail) itself, allowing to
     *   retrieve product data as it was at the time of placing the order.
     * - already adds the product record to the array we use as the {@see $shopObject}.
     */
    protected function createProduct(): ?Product
    {
        return null;
    }
}
