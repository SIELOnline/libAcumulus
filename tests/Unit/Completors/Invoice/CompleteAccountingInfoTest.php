<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteAccountingInfoTest tests
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteAccountingInfo}.
 */
class CompleteAccountingInfoTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public static function invoiceNumberDataProvider(): array
    {
        $ccpm = ['ideal' => 345, 'paypal' => 234];
        $acpm = ['ideal' => 678, 'paypal' => 567];
        return [
            ['paypal', 123, 789, $ccpm, $acpm, 234, 567],
            ['ideal', 123, 789, $ccpm, $acpm, 345, 678],
            ['card', 123, 789, $ccpm, $acpm, 123, 789],
            ['card', 123, 789, [], [], 123, 789],
            ['paypal', null, null, $ccpm, $acpm, 234, 567],
            ['ideal', null, null, $ccpm, $acpm, 345, 678],
            ['card', null, null, $ccpm, $acpm, null, null],
            ['paypal', 123, 789, $ccpm, $acpm, 890, 901, 890, 901],
        ];
    }

    /**
     * @dataProvider invoiceNumberDataProvider
     *
     * @param int[] $costCenterPerPaymentMethod
     * @param int[] $accountNumberPerPaymentMethod
     *
     * @noinspection PhpTooManyParametersInspection
     */
    public function testComplete(
        int|string $paymentMethod,
        ?int $defaultCostCenter,
        ?int $defaultAccountNumber,
        array $costCenterPerPaymentMethod,
        array $accountNumberPerPaymentMethod,
        ?int $expectedCostCenter,
        ?int $expectedAccountNumber,
        ?int $filledInCostCenter = null,
        ?int $filledInAccountNumber = null
    ): void
    {
        $config = self::getContainer()->getConfig();
        $config->set('defaultCostCenter', $defaultCostCenter);
        $config->set('defaultAccountNumber', $defaultAccountNumber);
        $config->set('paymentMethodCostCenter', $costCenterPerPaymentMethod);
        $config->set('paymentMethodAccountNumber', $accountNumberPerPaymentMethod);
        $completor = self::getContainer()->getCompletorTask('Invoice','AccountingInfo');
        $invoice = $this->getInvoice();
        if ($filledInCostCenter !== null) {
            $invoice->costCenter = $filledInCostCenter;
        }
        if ($filledInAccountNumber !== null) {
            $invoice->accountNumber = $filledInAccountNumber;
        }
        $invoice->metadataSet(Meta::PaymentMethod, $paymentMethod);
        $completor->complete($invoice);
        self::assertSame($expectedCostCenter, $invoice->costCenter);
        self::assertSame($expectedAccountNumber, $invoice->accountNumber);
    }
}
