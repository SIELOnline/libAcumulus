<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Invoice\Currency;
use Siel\Acumulus\Invoice\Totals;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteConvertTotalsToEuroTest tests converting the Totals metadata field to euro.
 */
class CompleteConvertTotalsToEuroTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public static function convertToEuroDataProvider(): array
    {
        return [
            [[], [null, 100.0, 21.0], [121.0, 100.0, 21.0]],
            [['GBP', 1.15, false], [null, 100.0, 21.0], [121.0, 100.0, 21.0]],
            [['GBP', 1.2, true], [null, 100.0, 21.0], [145.2, 120.0, 25.2]],
            [['EUR', 1.0, true], [null, 100.0, 21.0], [121.0, 100.0, 21.0]],
        ];
    }

    /**
     * @dataProvider convertToEuroDataProvider
     *
     * @param array $currencyArgs
     * @param array $totalsArgs
     * @param array $expected
     */
    #[DataProvider('convertToEuroDataProvider')]
    public function testComplete(
        array $currencyArgs,
        array $totalsArgs,
        array $expected
    ): void {
        $completor = self::getContainer()->getCompletorTask('Invoice','ConvertTotalsToEuro');
        $invoice = $this->getInvoice();
        $invoice->metadataSet(Meta::Currency, new Currency(...$currencyArgs));
        $invoice->metadataSet(Meta::Totals, new Totals(...$totalsArgs));
        $completor->complete($invoice);
        /** @var Totals $totals */
        $totals = $invoice->metadataGet(Meta::Totals);
        $expected = array_combine(['amountInc', 'amountVat', 'amountEx'], $expected);
        foreach ($expected as $property => $value) {
            /** @noinspection PhpVariableVariableInspection */
            self::assertEqualsWithDelta($value, $totals->$property, 1e-6);
        }
    }
}
