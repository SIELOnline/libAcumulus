<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteInvoiceNumberTest tests the
 * {@see \Siel\Acumulus\Completors\Invoice\CompleteIssueDate} class.
 */
class CompleteIssueDateTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public static function issueDateDataProvider(): array
    {
        $date20220203 = '2022-02-03';
        $date20230405 = '2023-04-05';
        $date20240506 = '2024-05-06';
        $dateTime20220203 = DateTimeImmutable::createFromFormat('Y-m-d', $date20220203)->setTime(0, 0, 0);
        $dateTime20230405 = DateTimeImmutable::createFromFormat('Y-m-d', $date20230405)->setTime(0, 0, 0);
        $dateTime20240506 = DateTimeImmutable::createFromFormat('Y-m-d', $date20240506)->setTime(0, 0, 0);
        return [
            [Config::IssueDateSource_Transfer, $date20220203, $date20230405, null],
            [Config::IssueDateSource_Transfer, $date20220203, $date20230405, $dateTime20240506, $date20240506],
            [Config::IssueDateSource_OrderCreate, $date20220203, $date20230405, $dateTime20220203],
            [Config::IssueDateSource_OrderCreate, $date20220203, $date20230405, $dateTime20240506, $dateTime20240506],
            [Config::IssueDateSource_InvoiceCreate, $date20220203, $date20230405, $dateTime20230405],
            [Config::IssueDateSource_InvoiceCreate, $date20220203, null, $dateTime20220203],
            [Config::IssueDateSource_InvoiceCreate, $date20220203, $date20220203, $dateTime20240506, $dateTime20240506],
            [Config::IssueDateSource_OrderCreate, null, $date20230405, null],
            [Config::IssueDateSource_OrderCreate, $date20220203, null, $dateTime20220203],
            [Config::IssueDateSource_InvoiceCreate, null, null, null],
        ];
    }

    /**
     * @dataProvider issueDateDataProvider
     */
    public function testComplete(
        int $issueDateSource,
        ?string $orderDate,
        ?string $invoiceDate,
        ?DateTimeInterface $expected,
        DateTimeInterface|string|null $filledIn = null
    ): void {
        $config = self::getContainer()->getConfig();
        $config->set('dateToUse', $issueDateSource);
        $completor = self::getContainer()->getCompletorTask('Invoice','IssueDate');
        $invoice = $this->getInvoice();
        if ($filledIn !== null) {
            $invoice->issueDate = $filledIn;
        }
        $invoice->metadataSet(Meta::SourceDate, $orderDate);
        $invoice->metadataSet(Meta::ShopInvoiceDate, $invoiceDate);
        $completor->complete($invoice, $issueDateSource);
        $this->assertEquals($expected, $invoice->issueDate);
    }
}
