<?php

declare(strict_types=1);

namespace Siel\Acumulus\Product;

use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\Result;

/**
 * Extension of {@see Result} with properties and features specific to the
 * StockTransaction web API service call.
 */
class StockTransactionResult extends Result
{
    // Stock transaction handling related constants.
    // Reasons for not sending.
    public const NotSent_StockManagementNotEnabled = 0x1;
    public const NotSent_StockManagementDisabledForProduct = 0x2;
    public const NotSent_NoProduct = 0x3;
    public const NotSent_ZeroChange = 0x4;
    public const NotSent_NoMatchValueInProduct = 0x5;
    public const NotSent_NoMatchInAcumulus = 0x6;
    public const NotSent_TooManyMatchesInAcumulus = 0x7;

    /**
     * @var \Siel\Acumulus\Data\StockTransaction|null
     *   The stock transaction that is (attempted to) being sent to Acumulus,
     *   or null if not yet set.
     */
    protected ?StockTransaction $stockTransaction = null;

    /**
     * Returns a translated string indicating the reason for the action taken.
     */
    protected function getStatusMessages(): array
    {
        return [
            self::NotSent_StockManagementNotEnabled => 'reason_not_sent_not_enabled',
            self::NotSent_StockManagementDisabledForProduct => 'reason_not_sent_disabled_product',
            self::NotSent_NoProduct => 'reason_not_sent_no_product',
            self::NotSent_ZeroChange => 'reason_not_sent_zero_change',
            self::NotSent_NoMatchValueInProduct => 'reason_not_sent_no_value_to_match',
            self::NotSent_NoMatchInAcumulus => 'reason_not_sent_no_match_in_acumulus',
            self::NotSent_TooManyMatchesInAcumulus => 'reason_not_sent_multiple_matches_in_acumulus',
            self::Sent_New => 'reason_sent',
        ] + parent::getStatusMessages();
    }

    /**
     * Returns the stock transaction that is (attempted to) being sent to Acumulus,
     * or null if not yet set.
     */
    public function getStockTransaction(): ?StockTransaction
    {
        return $this->stockTransaction;
    }

    public function setStockTransaction(StockTransaction $stockTransaction): void
    {
        $this->stockTransaction = $stockTransaction;
    }
}
