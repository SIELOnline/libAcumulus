<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Helpers\MessageCollection;

/**
 * StockTransactionCompletor completes an {@see \Siel\Acumulus\Data\StockTransaction}.
 *
 * After a stock transaction has been collected, the shop-specific part, it may need to be
 * completed shop independently.
 *
 * @noinspection PhpUnused  Instantiated by type name
 */
class StockTransactionCompletor extends BaseCompletor
{
    /**
     * Completes an {@see \Siel\Acumulus\Data\StockTransaction}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param \Siel\Acumulus\Data\StockTransaction $acumulusObject
     * @param \Siel\Acumulus\Helpers\MessageCollection $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        $this->getContainer()->getCompletorTask('StockTransaction', 'ByConfig')->complete($acumulusObject);
    }
}
