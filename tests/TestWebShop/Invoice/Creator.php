<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Invoice;

/**
 * Creator implements test websop specific Creator features.
 */
class Creator extends \Siel\Acumulus\Invoice\Creator
{

    /**
     * @inheritDoc
     */
    protected function getShippingLine(): array
    {
        return [];
    }
}
