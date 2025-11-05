<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Utils;

use Siel\Acumulus\Helpers\Container;

/**
 * AcumulusContainer contains {@see Container Acumulus Container} related features
 * for library and shop tests.
 */
trait AcumulusContainer
{
    private static Container $container;

    /**
     * Creates a container for the 'TestWebShop' namespace with 'nl' as language.
     *
     * Override if the test needs another container.
     */
    protected static function createContainer(): Container
    {
        return new Container('TestWebShop', 'nl');
    }

    /**
     * Returns an Acumulus Container instance.
     */
    protected static function getContainer(): Container
    {
        self::$container ??= self::createContainer();
        return self::$container;
    }
}
