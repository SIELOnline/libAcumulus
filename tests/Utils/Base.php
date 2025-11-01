<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use Siel\Acumulus\Helpers\Container;

use function dirname;
use function strlen;

/**
 * Base contains base test features for the various shop-specific test environments.
 *
 * - Container creation and getting.
 * - Path handling (to save and retrieve test data).
 */
trait Base
{
    private static Container $container;

    /**
     * Returns an Acumulus Container instance.
     */
    protected static function getContainer(): Container
    {
        self::$container ??= self::createContainer();
        return self::$container;
    }

    /**
     * Creates a container for the 'TestWebShop' namespace with 'nl' as language.
     *
     * Override if the test needs another container.
     */
    protected static function createContainer(): Container
    {
        $container = new Container('TestWebShop', 'nl');
        $container->addTranslations('Translations', 'Invoice');
        return $container;
    }

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
