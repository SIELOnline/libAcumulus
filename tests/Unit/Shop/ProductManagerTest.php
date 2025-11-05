<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Shop;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\TestWebShop\TestDoubles\Shop\ProductManager;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

/**
 * ProductManagerTest tests the ProductManager class.
 */
class ProductManagerTest extends TestCase
{
    use AcumulusContainer;

    protected static string $shopNamespace = 'TestWebShop\TestDoubles';

    private function getProductManager(): ProductManager
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return self::getContainer()->getProductManager();
    }

    public function testMatchAcumulusProductInListFailure(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $productManager = $this->getProductManager();
        self::getContainer()->getConfig()->get('productMatchAcumulusField');
        $acumulusField = self::getContainer()->getConfig()->set('productMatchAcumulusField', '');
        try {
            $productManager->getAcumulusProductByMatchField('TESTSKU');
        } finally {
            self::getContainer()->getConfig()->set('productMatchAcumulusField', $acumulusField);
        }
    }

    /**
     * @noinspection SpellCheckingInspection
     */
    public function testMatchAcumulusProductInListSuccess(): void
    {
        $productManager = $this->getProductManager();
        $acumulusField = self::getContainer()->getConfig()->set('productMatchAcumulusField', 'productsku');
        $product = $productManager->getAcumulusProductByMatchField('TESTSKU');
        self::assertSame('TESTSKU', $product[Fld::ProductSku]);
        self::assertSame('t-shirt rood', $product[Fld::ProductDescription]);
        self::getContainer()->getConfig()->set('productMatchAcumulusField', $acumulusField);
    }
}
