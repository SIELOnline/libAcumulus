<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Invoice\Currency;

/**
 * CurrencyTest tests the {@see Currency} class.
 */
class CurrencyTest extends TestCase
{
    public static function currencyProvider(): array
    {
        return [
            [false, 2.0, 2.0,],
            [false, 2.0, 2.0, 'GBP',],
            [false, 2.0, 2.0, 'GBP', 1.0, true],
            [false, 2.0, 2.0, 'GBP', 2.0,],
            [true, 2.0, 4.0, 'GBP', 2.0, true],
            [true, null, null, 'GBP', 2.0, true],
        ];
    }

    /**
     * Tests converting amounts.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('currencyProvider')]
    public function testConvertAmount(
        bool $shouldConvert,
        ?float $amount,
        ?float $convertedAmount,
        string $currency = 'EUR',
        float $rate = 1.0,
        bool $doConvert = false
    ):
    void {
        $currency = new Currency($currency, $rate, $doConvert);
        static::assertSame($shouldConvert, $currency->shouldConvert());
        static::assertEqualsWithDelta($convertedAmount, $currency->convertAmount($amount), 0.005);
    }
}
