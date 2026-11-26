<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Line;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteVatRangeTest tests {@see \Siel\Acumulus\Completors\Line\CompleteVatRange}.
 */
class CompleteVatRangeTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public function testNoCompletionHasVatRate(): void
    {
        $line = $this->getLine(LineType::Item);
        $line->unitPrice = 10;
        $line->vatRate = 21.0;
        $line->metadataSet(Meta::VatAmount, 2.1);
        $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        static::assertFalse($line->metadataExists(Meta::VatRateSource));
        static::assertFalse($line->metadataExists(Meta::VatRateMin));
        static::assertFalse($line->metadataExists(Meta::VatRateMax));
    }

    public function testNoCompletionHasNoVatAmount(): void
    {
        $line = $this->getLine(LineType::Item);
        $line->unitPrice = 10;
        $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        static::assertFalse($line->metadataExists(Meta::VatRateSource));
        static::assertFalse($line->metadataExists(Meta::VatRateMin));
        static::assertFalse($line->metadataExists(Meta::VatRateMax));
    }

    public function testNoCompletionHasNoPrecision(): void
    {
        $line = $this->getLine(LineType::Item);
        $line->unitPrice = 10;
        $line->metadataSet(Meta::VatAmount, 2.1);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        static::assertFalse($line->metadataExists(Meta::VatRateSource));
        static::assertFalse($line->metadataExists(Meta::VatRateMin));
        static::assertFalse($line->metadataExists(Meta::VatRateMax));

        $line = $this->getLine(LineType::Item);
        $line->unitPrice = 10;
        $line->metadataSet(Meta::VatAmount, 2.1);
        $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        static::assertFalse($line->metadataExists(Meta::VatRateSource));
        static::assertFalse($line->metadataExists(Meta::VatRateMin));
        static::assertFalse($line->metadataExists(Meta::VatRateMax));
    }

    public static function vatRangeWithUnitPriceDataProvider(): array
    {
        return [
            [10, 2.1, VatRateSource::Calculated, 20.9334, 21.0668, null],
            [10, 0, VatRateSource::No_Vat_Detected, null, null, 0],
            [0, 0, VatRateSource::Completor, null, null, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('vatRangeWithUnitPriceDataProvider')]
    public function testCompletionWithUnitPrice(
        float $unitPrice,
        float $vatAmount,
        ?string $vatRateSource,
        ?float $min,
        ?float $max,
        ?float $rate
    ): void {
        $line = $this->getLine(LineType::Item);
        $line->unitPrice = $unitPrice;
        $line->metadataSet(Meta::VatAmount, $vatAmount);
        $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        static::assertSame($vatRateSource, $line->metadataGet(Meta::VatRateSource));
        if ($min !== null) {
            static::assertEqualsWithDelta($min, $line->metadataGet(Meta::VatRateMin), 0.0001);
        } else {
            static::assertFalse($line->metadataExists(Meta::VatRateMin));
        }
        if ($max !== null) {
            static::assertEqualsWithDelta($max, $line->metadataGet(Meta::VatRateMax), 0.0001);
        } else {
            static::assertFalse($line->metadataExists(Meta::VatRateMax));
        }
        if ($rate !== null) {
            static::assertEqualsWithDelta($rate, $line->vatRate, 0.0001);
        } else {
            static::assertNull($line->vatRate);
        }
    }

    public static function vatRangeWithUnitPriceIncDataProvider(): array
    {
        return [
            [12.1, 2.1, VatRateSource::Calculated, 20.9218, 21.0783, null],
            [10, 0, VatRateSource::No_Vat_Detected, null, null, 0],
            [0, 0, VatRateSource::Completor, null, null, null],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('vatRangeWithUnitPriceIncDataProvider')]
    public function testCompletionWithUnitPriceInc(
        float $unitPriceInc,
        float $vatAmount,
        ?string $vatRateSource,
        ?float $min,
        ?float $max,
        ?float $rate
    ): void {
        $line = $this->getLine(LineType::Item);
        $line->metadataSet(Meta::UnitPriceInc, $unitPriceInc);
        $line->metadataSet(Meta::VatAmount, $vatAmount);
        $line->metadataSet(Meta::PrecisionUnitPriceInc, 0.01);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        static::assertSame($vatRateSource, $line->metadataGet(Meta::VatRateSource));
        if ($min !== null) {
            static::assertEqualsWithDelta($min, $line->metadataGet(Meta::VatRateMin), 0.0001);
        } else {
            static::assertFalse($line->metadataExists(Meta::VatRateMin));
        }
        if ($max !== null) {
            static::assertEqualsWithDelta($max, $line->metadataGet(Meta::VatRateMax), 0.0001);
        } else {
            static::assertFalse($line->metadataExists(Meta::VatRateMax));
        }
        if ($rate !== null) {
            static::assertEqualsWithDelta($rate, $line->vatRate, 0.0001);
        } else {
            static::assertNull($line->vatRate);
        }
    }
}
