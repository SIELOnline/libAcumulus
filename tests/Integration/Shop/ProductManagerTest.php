<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Product\StockTransactionResult;
use Siel\Acumulus\Shop\ProductManager;
use PHPUnit\Framework\TestCase;

/**
 * ProductManagerTest tests the {@see ProductManager}.
 *
 * This is an integration test, thus end-to-end with communication with the SIEL API.
 */
class ProductManagerTest extends TestCase
{
    private static Container $container;

    protected static function getAcumulusContainer(): Container
    {
        if (!isset(self::$container)) {
            self::$container = new Container('TestWebShop', 'nl');
            self::$container->addTranslations('Translations', 'Invoice');
        }
        return self::$container;
    }

    private function getProductManager(): ProductManager
    {
        return $this->getAcumulusContainer()->getProductManager();
    }

//    public function testGetAcumulusProductByReference()
//    {
//    }

    public function itemProvider(): array
    {
        return [
            'item 3 sku' => [3, 13, Fld::ProductSku, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 3 ean' => [3, 13, Fld::ProductEan, 1833637],
            'item 3 free' => [3, 13, '', 1833637],
            'item 4 sku' => [4, 14, Fld::ProductSku, StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 4 ean' => [4, 14, Fld::ProductEan, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 4 free' => [4, 14, '', StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
            'item 5 sku' => [5, 15, Fld::ProductSku, 1833642],
            'item 5 ean' => [5, 15, Fld::ProductEan, StockTransactionResult::NotSent_NoMatchInAcumulus],
            'item 5 free' => [5, 15, '', 1833642],
            'item 6 sku' => [6, 16, Fld::ProductSku, 1833638],
            'item 6 ean' => [6, 16, Fld::ProductEan, 1833636],
            'item 6 free' => [6, 16, '', StockTransactionResult::NotSent_TooManyMatchesInAcumulus],
        ];
    }

    /**
     * @dataProvider itemProvider
     */
    public function testUpdateStock(int $itemId, int $productId, string $acumulusField, int $acumulusProductIdOrError): void
    {
        $config = $this->getAcumulusContainer()->getConfig();
        $source = $this->getAcumulusContainer()->createSource(Source::Order, 1);
        $item = $this->getAcumulusContainer()->createItem($itemId, $source);
        $product = $item->getProduct();
        self::assertSame($itemId, $item->getId());
        self::assertSame($productId, $product->getId());

        $productMatchShopField = $config->set('productMatchShopField', '[product::getShopObject()::sku]');
        $productMatchAcumulusField = $config->set('productMatchAcumulusField', $acumulusField);
        $productManager = $this->getProductManager();
        $result = $productManager->updateStockForItem($item, 4, __METHOD__);
        if ($result->isSendingPrevented()) {
            self::assertSame($acumulusProductIdOrError, $result->getSendStatus());
        } else {
            self::assertSame($acumulusProductIdOrError, (int) $result->getMainApiResponse()[Fld::ProductId]);
            $stockAmount = $result->getMainApiResponse()[Fld::StockAmount];
            $result = $productManager->updateStockForItem($item, -4, __METHOD__);
            self::assertSame($acumulusProductIdOrError, (int) $result->getMainApiResponse()[Fld::ProductId]);
            self::assertSame($stockAmount - 4.0, (float) $result->getMainApiResponse()[Fld::StockAmount]);
        }
        $config->set('productMatchShopField', $productMatchShopField);
        $config->set('productMatchAcumulusField', $productMatchAcumulusField);
    }
}
