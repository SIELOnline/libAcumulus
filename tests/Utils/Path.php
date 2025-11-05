<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use function dirname;
use function strlen;

/**
 * Path contains path retrieving  features for library and shop tests.
 */
trait Path
{
    use AcumulusContainer;

    /**
     * Returns the path to the 'tests' folder.
     *
     * To be overridden when used in webshop specific tests
     */
    protected function getTestsPath(): string
    {
        return dirname(__FILE__, 2);
    }

    /**
     * Returns the path to the data folder of the actual web shop.
     */
    protected function getDataPath(): string
    {
        $shopNamespace = self::getContainer()->getShopNamespace();
        if (str_starts_with($shopNamespace, 'TestWebShop')) {
            $shopNamespace = '';
        } elseif (str_ends_with($shopNamespace, '\TestWebShop')) {
            $shopNamespace = substr($shopNamespace, 0, -strlen('\TestWebShop'));
        }
        return $this->getTestsPath() . "/$shopNamespace/Data";
    }
}
