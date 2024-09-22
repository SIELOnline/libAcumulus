<?php
/**
 * @noinspection PhpMultipleClassesDeclarationsInOneFile
 * @noinspection EmptyClassInspection
 * @noinspection PhpIllegalPsrClassPathInspection
 * @noinspection PhpMissingDocCommentInspection
 *
 * This stub contains definitions for classes and methods that are named differently in
 * OC3 versus OC4. It also contains some const values that consistently give false
 * positives on PHPStorm's code analysis.
 */

declare(strict_types=1);

const DB_PREFIX = 'oc_';

/**
 * @method array getTotals(int $order_id) OC4
 * @method array getOrderTotals(int $order_id) OC3
 * @method array getProducts(int $order_id) OC4
 * @method array getOrderProducts(int $order_id) OC3
 * @method array getOptions(int $order_id, int $order_product_id) OC4
 * @method array getOrderOptions(int $order_id, int $order_product_id) OC3
 */
class ModelCheckoutOrder
{
}

/**
 * @method array getTotals(int $order_id) OC4
 * @method array getOrderTotals(int $order_id) OC3
 * @method array getProducts(int $order_id) OC4
 * @method array getOrderProducts(int $order_id) OC3
 * @method array getOptions(int $order_id, int $order_product_id) OC4
 * @method array getOrderOptions(int $order_id, int $order_product_id) OC3
 */
class ModelSaleOrder
{
}

class ModelCatalogProduct
{
}

// remove, because it interferes with prestaShop's Db
class DB
{
}
