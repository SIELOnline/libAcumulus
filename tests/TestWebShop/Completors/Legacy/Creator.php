<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Completors\Legacy;

/**
 * Creator contains legacy Creator functions for TestWebShop.
 */
class Creator extends \Siel\Acumulus\Completors\Legacy\Creator
{
    protected function getShippingLine(): array
    {
        return [];
    }
}
