<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Shop;

use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Invoice\Source;
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

    public function testUpdateStockForItem(): void
    {
        $source = $this->getAcumulusContainer()->createSource(Source::Order, 1);
        $item = $this->getAcumulusContainer()->createItem(2, $source);
        $product = $item->getProduct();
        $productManager = $this->getProductManager();
        // This update will need a call to getAcumulusProductByReference() to set the acumulusId.
        $result = $productManager->updateStockForItem($item, 4, __METHOD__);
        self::assertFalse($result->hasError());
        $response = $result->getMainApiResponse();
        self::assertIsArray($response);
        self::assertArrayHasKey('stockamount', $response);
        $stockAmount = (int) $response['stockamount'];
        self::assertSame($product->getAcumulusId(), (int) $response['productid']);

        // This update doesn't need a call to getAcumulusProductByReference()
        $result = $productManager->updateStockForItem($item, -4, __METHOD__);
        self::assertFalse($result->hasError());
        $response = $result->getMainApiResponse();
        self::assertIsArray($response);
        self::assertArrayHasKey('stockamount', $response);
        self::assertSame($stockAmount - 4, (int) $response['stockamount']);
        self::assertSame($product->getAcumulusId(), (int) $response['productid']);
    }
}
