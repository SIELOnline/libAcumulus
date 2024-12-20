<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Shop;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\TestWebShop\TestDoubles\Shop\ProductManager;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Tests\Unit\ApiClient\ApiRequestResponseExamples;
use UnexpectedValueException;

/**
 * ProductManagerTest tests the ProductManager class.
 */
class ProductManagerTest extends TestCase
{
    private Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $language = 'nl';
        $this->container = new Container('TestWebShop\TestDoubles', $language);
        $this->examples =  ApiRequestResponseExamples::getInstance();
    }

    private function getProductManager(): ProductManager
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->container->getProductManager();
    }

    public function testMatchAcumulusProductInListFailure(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $productManager = $this->getProductManager();
        $this->container->getConfig()->get('productMatchAcumulusField');
        $acumulusField = $this->container->getConfig()->set('productMatchAcumulusField', '');
        try {
            $productManager->getAcumulusProductByMatchField('TESTSKU');
        } finally {
            $this->container->getConfig()->set('productMatchAcumulusField', $acumulusField);
        }
    }

    public function testMatchAcumulusProductInListSuccess(): void
    {
        $productManager = $this->getProductManager();
        $acumulusField = $this->container->getConfig()->set('productMatchAcumulusField', 'productsku');
        $product = $productManager->getAcumulusProductByMatchField('TESTSKU');
        self::assertSame('TESTSKU', $product[Fld::ProductSku]);
        self::assertSame('t-shirt rood', $product[Fld::ProductDescription]);
        $this->container->getConfig()->set('productMatchAcumulusField', $acumulusField);
    }
}
