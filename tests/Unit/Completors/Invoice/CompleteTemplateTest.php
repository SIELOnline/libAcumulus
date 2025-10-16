<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;

/**
 * CompleteTemplateTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteTemplate} class.
 */
class CompleteTemplateTest extends TestCase
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

    private function getInvoice(): Invoice
    {
        /** @var \Siel\Acumulus\Data\Invoice $invoice */
        $invoice = $this->getContainer()->createAcumulusObject(DataType::Invoice);
        return $invoice;
    }

    public static function templateDataProvider(): array
    {
        return [
            [Api::PaymentStatus_Due, 123, 789, 123],
            [Api::PaymentStatus_Paid, 123, 789, 789],
            [Api::PaymentStatus_Due, 123, 0, 123],
            [Api::PaymentStatus_Paid, 123, 0, 123],
            [Api::PaymentStatus_Due, 123, 789, 456, 456],
            [Api::PaymentStatus_Paid, 123, 0, 567, 567],
        ];
    }

    /**
     * @dataProvider templateDataProvider
     *
     * @param int $paymentStatus
     * @param int $defaultInvoiceTemplate
     * @param int $defaultInvoicePaidTemplate
     * @param int $expected
     * @param ?int $filledIn
     *
     * @todo: add cases where template has already been filled in.
     *    and do the same for all other completors...
     */
    public function testComplete(
        int $paymentStatus,
        int $defaultInvoiceTemplate,
        int $defaultInvoicePaidTemplate,
        int $expected,
        ?int $filledIn = null
    ): void {
        $config = $this->getContainer()->getConfig();
        $config->set('defaultInvoiceTemplate', $defaultInvoiceTemplate);
        $config->set('defaultInvoicePaidTemplate', $defaultInvoicePaidTemplate);
        $completor = $this->getContainer()->getCompletorTask('Invoice','Template');
        $invoice = $this->getInvoice();
        $invoice->paymentStatus = $paymentStatus;
        if ($filledIn !== null) {
            $invoice->template = $filledIn;
        }
        $completor->complete($invoice);
        $this->assertEquals($expected, $invoice->template);
    }
}
