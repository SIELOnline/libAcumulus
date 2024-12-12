<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

/**
 * Container does foo.
 */
class Container extends \Siel\Acumulus\Helpers\Container
{
    public static int $count = 0;
    public function getInstance(string $class, string $subNamespace, array $constructorArgs = [], bool $newInstance = false): ?object
    {
        self::$count++;
        return parent::getInstance($class, $subNamespace, $constructorArgs, $newInstance);
    }

}
