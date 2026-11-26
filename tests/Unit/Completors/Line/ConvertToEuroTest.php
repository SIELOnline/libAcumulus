<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Line;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * ConvertToEuroTest tests the {@see CompleteConvertToEuro} line completor.
 */
class ConvertToEuroTest extends TestCase
{
    use AcumulusContainer;
    public static function currencyProvider(): array
    {
        return [
            new Currency(),
            new Currency('GBP', 1.5, false),
            new Currency('GBP', 1.5, true),
        ];
    }

    public static function lineProvider(): array
    {
        $line1 = new Line();
        $line1->unitPrice = 2.0;
        $line1->quantity = 3;
        $line1->costPrice = 1.5;
        $line1->metadataSet(Meta::VatAmount, 0.2);
        $line1->metadataSet(Meta::UnitPriceInc, 2.2);
        $line1->metadataSet(Meta::LineAmount, 6.0);
        $line1->metadataSet(Meta::LineAmountInc, 6.6);
        $line1->metadataSet(Meta::LineVatAmount, 0.6);
        $line1->metadataSet(Meta::LineDiscountAmount, 0.1);
        $line1->metadataSet(Meta::LineDiscountAmountInc, 0.11);
        $line1->metadataSet(Meta::LineDiscountVatAmount, 0.01);

        $line2 = new Line();
        $line2->unitPrice = 4.0;
        $line2->quantity = 5;
        $line2->metadataSet(Meta::VatAmount, 0.4);
        $line2->metadataSet(Meta::UnitPriceInc, 4.4);
        $line2->metadataSet(Meta::LineAmount, 20.0);
        $line2->metadataSet(Meta::LineAmountInc, 22.0);
        $line2->metadataSet(Meta::LineVatAmount, 2.0);

        return [$line1, $line2];
    }

    public static function expectedProvider(): array
    {
        $result1f = [2.0, 3.0, 1.5, 0.2, 2.2, 6.0, 6.6, 0.6, 0.1, 0.11, 0.01];
        $result1t = [3.0, 3.0, 2.25, 0.3, 3.3, 9.0, 9.9, 0.9, 0.15, 0.165, 0.015];

        $result2f = [4.0, 5.0, null, 0.4, 4.4, 20.0, 22, 2, null, null, null];
        $result2t = [6.0, 5.0, null, 0.6, 6.6, 30.0, 33, 3, null, null, null];

        return [$result1f, $result1f, $result1t, $result2f, $result2f, $result2t];
    }

    public static function combinedDataProvider(): array
    {
        $c = static::currencyProvider();
        $l = static::lineProvider();
        $r = static::expectedProvider();
        return [
            [$c[0], $l[0], $r[0]],
            [$c[1], $l[0], $r[1]],
            [$c[2], $l[0], $r[2]],
            [$c[0], $l[1], $r[3]],
            [$c[1], $l[1], $r[4]],
            [$c[2], $l[1], $r[5]],
        ];
    }

    /**
     * Tests converting amounts.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('combinedDataProvider')]
    public function testConvertAmount(Currency $currency, Line $line, array $expected): void
    {
        $completor = self::getContainer()->getCompletorTask('Line', 'ConvertToEuro');
        $completor->complete($line, $currency);
        static::assertEqualsWithDelta($expected[0], $line->unitPrice, 0.005);
        static::assertSame($expected[1], $line->quantity);
        if ($expected[2] === null) {
            static::assertNull($line->costPrice);
        } else {
            static::assertEqualsWithDelta($expected[2], $line->costPrice, 0.005);
        }
        static::assertEqualsWithDelta($expected[3], $line->metadataGet(Meta::VatAmount), 0.005);
        static::assertEqualsWithDelta($expected[4], $line->metadataGet(Meta::UnitPriceInc), 0.005);
        static::assertEqualsWithDelta($expected[5], $line->metadataGet(Meta::LineAmount), 0.005);
        static::assertEqualsWithDelta($expected[6], $line->metadataGet(Meta::LineAmountInc), 0.005);
        static::assertEqualsWithDelta($expected[7], $line->metadataGet(Meta::LineVatAmount), 0.005);
        if ($expected[8] === null) {
            static::assertNull($line->metadataGet(Meta::LineDiscountAmount));
        } else {
            static::assertEqualsWithDelta($expected[8], $line->metadataGet(Meta::LineDiscountAmount), 0.005);
        }
        if ($expected[9] === null) {
            static::assertNull($line->metadataGet(Meta::LineDiscountAmountInc));
        } else {
            static::assertEqualsWithDelta($expected[9], $line->metadataGet(Meta::LineDiscountAmountInc), 0.005);
        }
        if ($expected[10] === null) {
            static::assertNull($line->metadataGet(Meta::LineDiscountVatAmount));
        } else {
            static::assertEqualsWithDelta($expected[10], $line->metadataGet(Meta::LineDiscountVatAmount), 0.005);
        }
    }
}
