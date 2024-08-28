<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Line;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\Customer;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * CompleteByConfigTest tests {@see \Siel\Acumulus\Completors\Line\CompleteByConfig}.
 */
class CompleteByConfigTest extends TestCase
{
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
        $line->metadataSet(Meta::SubType, $lineType);
        return $line;
    }

    public function natureConfigDataProvider(): array
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
        $config = $this->getContainer()->getConfig();
        $config->set('nature_shop', $natureShop);
        $completor = $this->getContainer()->getCompletorTask('Line','ByConfig');
        $line = $this->getLine($lineType);
        $line->nature = $natureBefore;
        $completor->complete($line);
        $this->assertSame($natureExpected, $line->nature);
    }
}
