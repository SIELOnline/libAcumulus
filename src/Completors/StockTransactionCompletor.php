<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors;

use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\MessageCollection;

use Siel\Acumulus\Product\StockTransactionResult;

use function assert;

/**
 * StockTransactionCompletor completes an {@see StockTransaction}.
 *
 * After a stock transaction has been collected, the shop-specific part, it may need to be
 * completed shop independently.
 *
 * @noinspection PhpUnused  Instantiated by type name
 */
class StockTransactionCompletor extends BaseCompletor
{
    /**
     * Completes an {@see StockTransaction}.
     *
     * This phase is executed after the collecting phase.
     *
     * @param StockTransaction $acumulusObject
     * @param StockTransactionResult $result
     */
    public function complete(AcumulusObject $acumulusObject, MessageCollection $result): void
    {
        assert($acumulusObject instanceof StockTransaction);
        assert($result instanceof StockTransactionResult);

        $this->getContainer()->getCompletorTask('StockTransaction', 'ByConfig')->complete($acumulusObject);
    }
}
