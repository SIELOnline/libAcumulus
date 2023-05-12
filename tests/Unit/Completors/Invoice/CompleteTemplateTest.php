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

    public function templateDataProvider(): array
    {
        return [
            [Api::PaymentStatus_Due, 123, 789, 123],
            [Api::PaymentStatus_Paid, 123, 789, 789],
            [Api::PaymentStatus_Due, 123, 0, 123],
            [Api::PaymentStatus_Paid, 123, 0, 123],
        ];
    }

    /**
     * @dataProvider templateDataProvider
     *
     * @param int $paymentStatus
     * @param int $defaultInvoiceTemplate
     * @param int $defaultInvoicePaidTemplate
     * @param int $expected
     */
    public function testComplete(int $paymentStatus, int $defaultInvoiceTemplate, int $defaultInvoicePaidTemplate, int $expected): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set('defaultInvoiceTemplate', $defaultInvoiceTemplate);
        $config->set('defaultInvoicePaidTemplate', $defaultInvoicePaidTemplate);
        $completor = $this->getContainer()->getCompletorTask('Invoice','Template');
        $invoice = $this->getInvoice();
        $invoice->paymentStatus = $paymentStatus;
        $completor->complete($invoice);
        $this->assertEquals($expected, $invoice->template);
    }
}
