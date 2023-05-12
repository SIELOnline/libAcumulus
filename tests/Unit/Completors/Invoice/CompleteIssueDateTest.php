<?php
/**
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use DateTime;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\Invoice;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Meta;

/**
 * CompleteInvoiceNumberTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteIssueDate} class.
 */
class CompleteIssueDateTest extends TestCase
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

    public function issueDateDataProvider(): array
    {
        $date20220203 = '2022-02-03';
        $date20230405 = '2023-04-05';
        $dateTime20220203 = DateTime::createFromFormat('Y-m-d', $date20220203)->setTime(0, 0, 0);
        $dateTime20230405 = DateTime::createFromFormat('Y-m-d', $date20230405)->setTime(0, 0, 0);
        return [
            [Config::IssueDateSource_Transfer, $date20220203, $date20230405, null],
            [Config::IssueDateSource_OrderCreate, $date20220203, $date20230405, $dateTime20220203],
            [Config::IssueDateSource_InvoiceCreate, $date20220203, $date20230405, $dateTime20230405],
            [Config::IssueDateSource_OrderCreate, null, $date20230405, null],
            [Config::IssueDateSource_OrderCreate, $date20220203, null, $dateTime20220203],
            [Config::IssueDateSource_InvoiceCreate, null, null, null],
        ];
    }

    /**
     * @dataProvider issueDateDataProvider
     *
     * @param int $issueDateSource
     * @param string|null $orderDate
     * @param string|null $invoiceDate
     * @param \DateTime|null $expected
     */
    public function testComplete(int $issueDateSource, ?string $orderDate, ?string $invoiceDate, ?DateTime $expected): void
    {
        $config = $this->getContainer()->getConfig();
        $config->set('dateToUse', $issueDateSource);
        $completor = $this->getContainer()->getCompletorTask('Invoice','IssueDate');
        $invoice = $this->getInvoice();
        $invoice->metadataAdd(Meta::ShopSourceDate, $orderDate);
        $invoice->metadataAdd(Meta::ShopInvoiceDate, $invoiceDate);
        $completor->complete($invoice, $issueDateSource);
        $this->assertEquals($expected, $invoice->issueDate);
    }
}
