<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Line;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;

/**
 * CompleteByConfigTest tests {@see \Siel\Acumulus\Completors\Line\CompleteByConfig}.
 */
class CompleteByConfigTest extends TestCase
{
    use AcumulusContainer;

    private function getLine(string $lineType): Line
    {
        /** @var \Siel\Acumulus\Data\Line $line */
        $line = self::getContainer()->createAcumulusObject(DataType::Line);
        $line->metadataSet(Meta::SubType, $lineType);
        return $line;
    }

    public static function natureConfigDataProvider(): array
    {
        return [
            [Config::Nature_Unknown, LineType::Item, null, null,],
            [Config::Nature_Unknown, LineType::Item, Api::Nature_Product, Api::Nature_Product,],
            [Config::Nature_Both, LineType::Item, null, null,],
            [Config::Nature_Both, LineType::Item, Api::Nature_Service, Api::Nature_Service,],
            [Config::Nature_Products, LineType::Item, null, Api::Nature_Product,],
            [Config::Nature_Products, LineType::Item, Api::Nature_Service, Api::Nature_Service,],
            [Config::Nature_Services, LineType::Item, null, Api::Nature_Service,],
            [Config::Nature_Services, LineType::Item, Api::Nature_Product, Api::Nature_Product,],
            [Config::Nature_Products, LineType::Shipping, null, null,],
        ];
    }

    /**
     * @dataProvider natureConfigDataProvider
     */
    public function testComplete(int $natureShop, string $lineType, ?string $natureBefore, $natureExpected): void
    {
        $config = self::getContainer()->getConfig();
        $config->set('nature_shop', $natureShop);
        $completor = self::getContainer()->getCompletorTask('Line','ByConfig');
        $line = $this->getLine($lineType);
        $line->nature = $natureBefore;
        $completor->complete($line);
        $this->assertSame($natureExpected, $line->nature);
    }
}
