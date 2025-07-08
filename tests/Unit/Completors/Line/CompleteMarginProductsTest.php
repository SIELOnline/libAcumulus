<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Line;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * CompleteMarginSchemeTest tests {@see \Siel\Acumulus\Completors\Line\CompleteMarginProducts}.
 */
class CompleteMarginProductsTest extends TestCase
{
    private const MarginProducts = [
        Config::MarginProducts_Unknown,
        Config::MarginProducts_Both,
        Config::MarginProducts_No,
        Config::MarginProducts_Only,
    ];

    private Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
        $this->container->addTranslations('Translations', 'Invoice');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    private function getContainer(): Container
    {
        return $this->container;
    }

    private function getLine(string $lineType): Line
    {
        /** @var \Siel\Acumulus\Data\Line $line */
        $line = $this->getContainer()->createAcumulusObject(DataType::Line);
        $line->setType($lineType);
        return $line;
    }

    public static function marginProductsConfigDataProvider(): array
    {
        return [
            [
                LineType::Item,
                [Fld::CostPrice => 2.0, Fld::UnitPrice => 5.0, Meta::UnitPriceInc => 6.05],
                [Fld::CostPrice => 2.0, Fld::UnitPrice => 6.05, Meta::MarginLineOldUnitPrice => 5.0],
            ],
            [
                LineType::Shipping,
                [Fld::CostPrice => 2.0, Fld::UnitPrice => 5.0, Meta::UnitPriceInc => 6.05],
                [Fld::CostPrice => 2.0, Fld::UnitPrice => 6.05, Meta::MarginLineOldUnitPrice => 5.0],
            ],
        ];
    }

    /**
     * @dataProvider marginProductsConfigDataProvider
     */
    public function testComplete(string $lineType, array $lineValues, $lineValuesExpected): void
    {
        $config = $this->getContainer()->getConfig();
        $completor = $this->getContainer()->getCompletorTask('Line', 'MarginProducts');
        foreach (self::MarginProducts as $marginProduct) {
            $config->set('marginProducts', $marginProduct);
            $line = $this->getLine($lineType);
            foreach ($lineValues as $field => $lineValue) {
                if ($line->isProperty($field)) {
                    $line->$field = $lineValue;
                } else {
                    $line->metadataSet($field, $lineValue);
                }
            }
            $completor->complete($line);
            $method = $lineType === LineType::Item
                ? "marginProducts$marginProduct"
                : "marginProductsOtherLine";
            $this->$method($line, $lineValues);
        }
    }

    /**
     * 0 = Config::MarginProducts_Unknown
     */
    private function marginProducts0(Line $line, array $lineValues)
    {
        self::assertFalse($line->metadataGet(Meta::MarginLine));
        self::assertFalse($line->metadataExists(Meta::MarginLineOldUnitPrice));
        self::assertSame($lineValues[Fld::UnitPrice], $line->unitPrice);
        self::assertSame($lineValues[Meta::UnitPriceInc], $line->metadataGet(Meta::UnitPriceInc));
    }

    /**
     * 1 = Config::MarginProducts_Both
     */
    private function marginProducts1(Line $line, array $lineValues)
    {
        self::assertNull($line->metadataGet(Meta::MarginLine));
        self::assertSame($lineValues[Fld::UnitPrice], $line->metadataGet(Meta::MarginLineOldUnitPrice));
        self::assertSame($lineValues[Meta::UnitPriceInc], $line->unitPrice);
        self::assertSame($lineValues[Meta::UnitPriceInc], $line->metadataGet(Meta::UnitPriceInc));
    }

    /**
     * 2 = Config::MarginProducts_No
     */
    private function marginProducts2(Line $line, array $lineValues)
    {
        self::assertFalse($line->metadataGet(Meta::MarginLine));
        self::assertFalse($line->metadataExists(Meta::MarginLineOldUnitPrice));
        self::assertSame($lineValues[Fld::UnitPrice], $line->unitPrice);
        self::assertSame($lineValues[Meta::UnitPriceInc], $line->metadataGet(Meta::UnitPriceInc));
    }

    /**
     * 3 = Config::MarginProducts_Only
     */
    private function marginProducts3(Line $line, array $lineValues)
    {
        self::assertTrue($line->metadataGet(Meta::MarginLine));
        self::assertSame($lineValues[Fld::UnitPrice], $line->metadataGet(Meta::MarginLineOldUnitPrice));
        self::assertSame($lineValues[Meta::UnitPriceInc], $line->unitPrice);
        self::assertSame($lineValues[Meta::UnitPriceInc], $line->metadataGet(Meta::UnitPriceInc));
    }

    /**
     * 2 = Config::MarginProducts_No
     */
    private function marginProductsOtherLine(Line $line, array $lineValues)
    {
        self::assertFalse($line->metadataExists(Meta::MarginLine));
        self::assertFalse($line->metadataExists(Meta::MarginLineOldUnitPrice));
        self::assertSame($lineValues[Fld::UnitPrice], $line->unitPrice);
        self::assertSame($lineValues[Meta::UnitPriceInc], $line->metadataGet(Meta::UnitPriceInc));
    }
}
