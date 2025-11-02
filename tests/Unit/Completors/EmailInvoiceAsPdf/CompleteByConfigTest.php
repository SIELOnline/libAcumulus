<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\EmailInvoiceAsPdf;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Helpers\Container;

/**
 * CompleteByConfigTest tests {@see \Siel\Acumulus\Completors\EmailInvoiceAsPdf\CompleteByConfig}.
 */
class CompleteByConfigTest extends TestCase
{
    private Container $container;

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function setUp(): void
    {
        $this->container = new Container('TestWebShop', 'nl');
    }

    /**
     * @return \Siel\Acumulus\Helpers\Container
     */
    private function getContainer(): Container
    {
        return $this->container;
    }

    private function getEmailInvoiceAsPdf(): EmailInvoiceAsPdf
    {
        /** @var \Siel\Acumulus\Data\EmailInvoiceAsPdf $emailInvoiceAsPdf */
        $emailInvoiceAsPdf = $this->getContainer()->createAcumulusObject(DataType::EmailInvoiceAsPdf);
        return $emailInvoiceAsPdf;
    }

    public static function emailInvoiceAsPdfConfigDataProvider(): array
    {
        return [
            ['ubl', true, true],
            ['ubl', false, false],
            ['ubl', null, null],
            ['ubl', Api::UblInclude_Yes, true],
            ['ubl', Api::UblInclude_No, false],
        ];
    }

    /**
     * @dataProvider emailInvoiceAsPdfConfigDataProvider
     */
    public function testComplete(
        string $key,
        $value,
        ?bool $expectedValue
    ): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set($key, $value);
        $completor = $this->getContainer()->getCompletorTask('EmailInvoiceAsPdf','ByConfig');
        $emailAsPdf = $this->getEmailInvoiceAsPdf();
        $completor->complete($emailAsPdf);

        $this->assertSame($expectedValue, $emailAsPdf->ubl);
    }
}
