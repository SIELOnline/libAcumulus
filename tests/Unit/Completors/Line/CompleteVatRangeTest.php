<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Line;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Data\VatRateSource;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * CompleteVatRangeTest tests {@see \Siel\Acumulus\Completors\Line\CompleteVatRange}.
 */
class CompleteVatRangeTest extends TestCase
{
    use AcumulusContainer;

    private function getLine(string $lineType): Line
    {
        /** @var \Siel\Acumulus\Data\Line $line */
        $line = self::getContainer()->createAcumulusObject(DataType::Line);
        $line->metadataSet(Meta::SubType, $lineType);
        return $line;
    }

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
        self::assertFalse($line->metadataExists(Meta::VatRateSource));
        self::assertFalse($line->metadataExists(Meta::VatRateMin));
        self::assertFalse($line->metadataExists(Meta::VatRateMax));
    }

    public function testNoCompletionHasNoVatAmount(): void
    {
        $line = $this->getLine(LineType::Item);
        $line->unitPrice = 10;
        $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        self::assertFalse($line->metadataExists(Meta::VatRateSource));
        self::assertFalse($line->metadataExists(Meta::VatRateMin));
        self::assertFalse($line->metadataExists(Meta::VatRateMax));
    }

    public function testNoCompletionHasNoPrecision(): void
    {
        $line = $this->getLine(LineType::Item);
        $line->unitPrice = 10;
        $line->metadataSet(Meta::VatAmount, 2.1);
        $line->metadataSet(Meta::PrecisionVatAmount, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        self::assertFalse($line->metadataExists(Meta::VatRateSource));
        self::assertFalse($line->metadataExists(Meta::VatRateMin));
        self::assertFalse($line->metadataExists(Meta::VatRateMax));

        $line = $this->getLine(LineType::Item);
        $line->unitPrice = 10;
        $line->metadataSet(Meta::VatAmount, 2.1);
        $line->metadataSet(Meta::PrecisionUnitPrice, 0.01);

        $completor = self::getContainer()->getCompletorTask('Line', 'VatRange');
        $completor->complete($line);
        self::assertFalse($line->metadataExists(Meta::VatRateSource));
        self::assertFalse($line->metadataExists(Meta::VatRateMin));
        self::assertFalse($line->metadataExists(Meta::VatRateMax));
    }

    public static function vatRangeWithUnitPriceDataProvider(): array
    {
        return [
            [10, 2.1, VatRateSource::Calculated, 20.9323, 21.0678, null],
            [10, 0, VatRateSource::Exact0, null, null, 0],
            [0, 0, VatRateSource::Completor, null, null, null],
        ];
    }

    /**
     * @dataProvider vatRangeWithUnitPriceDataProvider
     */
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
        self::assertSame($vatRateSource, $line->metadataGet(Meta::VatRateSource));
        if ($min !== null) {
            self::assertEqualsWithDelta($min, $line->metadataGet(Meta::VatRateMin), 0.0001);
        } else {
            self::assertFalse($line->metadataExists(Meta::VatRateMin));
        }
        if ($max !== null) {
            self::assertEqualsWithDelta($max, $line->metadataGet(Meta::VatRateMax), 0.0001);
        } else {
            self::assertFalse($line->metadataExists(Meta::VatRateMax));
        }
        if ($rate !== null) {
            self::assertEqualsWithDelta($rate, $line->vatRate, 0.0001);
        } else {
            self::assertNull($line->vatRate);
        }
    }

    public static function vatRangeWithUnitPriceIncDataProvider(): array
    {
        return [
            [12.1, 2.1, VatRateSource::Calculated, 20.9206, 21.0796, null],
            [10, 0, VatRateSource::Exact0, null, null, 0],
            [0, 0, VatRateSource::Completor, null, null, null],
        ];
    }

    /**
     * @dataProvider vatRangeWithUnitPriceIncDataProvider
     */
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
        self::assertSame($vatRateSource, $line->metadataGet(Meta::VatRateSource));
        if ($min !== null) {
            self::assertEqualsWithDelta($min, $line->metadataGet(Meta::VatRateMin), 0.0001);
        } else {
            self::assertFalse($line->metadataExists(Meta::VatRateMin));
        }
        if ($max !== null) {
            self::assertEqualsWithDelta($max, $line->metadataGet(Meta::VatRateMax), 0.0001);
        } else {
            self::assertFalse($line->metadataExists(Meta::VatRateMax));
        }
        if ($rate !== null) {
            self::assertEqualsWithDelta($rate, $line->vatRate, 0.0001);
        } else {
            self::assertNull($line->vatRate);
        }
    }
}
