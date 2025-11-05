<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Integration\Mail;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\AcumulusResult;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\InvoiceAddMail;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Mail\CrashMail;
use Siel\Acumulus\Mail\Mail;
use Siel\Acumulus\Product\StockTransactionMail;
use Siel\Acumulus\Tests\AcumulusTestUtils;
use Siel\Acumulus\Tests\Data\GetTestData;
use Siel\Acumulus\Tests\Unit\ApiClient\ApiRequestResponseExamples;
use Siel\Acumulus\TestWebShop\Mail\Mailer;

/**
 * MailTest tests the creation and sending of the mails
 */
class MailTest extends TestCase
{
    use AcumulusTestUtils;

    protected static string $shopNamespace = 'TestWebShop\TestDoubles';
    protected RuntimeException $testException;
    private ApiRequestResponseExamples $examples;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function setUpBeforeClass(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'get';
    }

    protected function setUp(): void
    {
        $this->testException = new RuntimeException('Test exception');
        $this->examples = ApiRequestResponseExamples::getInstance();
        $this->examples->setOptions([Fld::Lang => static::getContainer()->getLanguage()]);
    }

    private function getConfig(): Config
    {
        return static::getContainer()->getConfig();
    }

    private function getAcumulusResult(string $uri): AcumulusResult
    {
        $submit = $this->examples->getSubmit($uri);
        $needContract = $this->examples->needContract($uri);
        return static::getContainer()->createAcumulusRequest()->execute($uri, $submit, $needContract);
    }

    private function getMail(string $type, string $namespace): Mail
    {
        return static::getContainer()->getMail($type, $namespace);
    }

    private function getMailer(): Mailer
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return static::getContainer()->getMailer();
    }

    private function getInvoiceSource(): Source
    {
        $objects = (new GetTestData())->getJson();
        $order = $objects->order;
        return static::getContainer()->createSource(Source::Order, $order);
    }

    public function testCrashMail(): void
    {
        $this->createAndSendMail('CrashMail', 'Mail', CrashMail::class, ['exception' => 'Test Exception']);
    }

    public static function invoiceAddMailProvider(): array
    {
        return [
            'success-no-messages' => ['success-no-messages', 'invoice-add', Config::Send_SendAndMailOnError, true],
            'success-messages' => ['success-messages', 'invoice-add', Config::Send_SendAndMail, false],
            'success-testmode' => ['success-testmode', 'invoice-add', Config::Send_TestMode, true],
            'concept' => ['concept', 'invoice-add-concept', Config::Send_SendAndMail, true],
        ];
    }

    /**
     * @dataProvider invoiceAddMailProvider
     */
    public function testInvoiceAddMail(string $name, string $uri, int $debug, bool $emailAsPdf): void
    {
        $type = 'InvoiceAddMail';
        $namespace = 'Invoice';
        $class = InvoiceAddMail::class;
        $invoiceSource = $this->getInvoiceSource();
        $invoiceAddResult = static::getContainer()->createInvoiceAddResult(__METHOD__ . "($name)");
        $invoiceAddResult->setSendStatus(InvoiceAddResult::Sent_New);

        $oldDebug = $this->getConfig()->set('debug', $debug);
        $oldEmailAsPdf = $this->getConfig()->set('emailAsPdf', $emailAsPdf);
        try {
            $this->examples->setOptions([Fld::TestMode => $debug === Config::Send_TestMode ? Api::TestMode_Test : Api::TestMode_Normal]);
            $apiResult = $this->getAcumulusResult($uri);
            $apiResult->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));
            $invoiceAddResult->setAcumulusResult($apiResult);
            $args = [
                'source' => $invoiceSource,
                'result' => $invoiceAddResult,
            ];
            $this->createAndSendMail($type, $namespace, $class, $args, $name);
        } finally {
            $this->getConfig()->set('debug', $oldDebug);
            $this->getConfig()->set('emailAsPdf', $oldEmailAsPdf);
        }
    }

    public static function stockTransactionMailProvider(): array
    {
        return [
            'success-no-messages' => ['success-no-messages', 'stock-transaction', Config::Send_SendAndMailOnError, -5],
            'success-messages' => ['success-messages', 'stock-transaction', Config::Send_SendAndMail, -7],
            'success-testmode' => ['success-testmode', 'stock-transaction', Config::Send_TestMode, -11],
            'error' => ['error', 'stock-transaction-404', Config::Send_SendAndMail, -13],
        ];
    }

    /**
     * @dataProvider stockTransactionMailProvider
     *
     * @todo: mail contents is now covered by the broader {@see ProductmanagerTest::testUpdateStock()}
     */
    public function testStockTransactionMail(string $name, string $uri, int $debug, int|float $change): void
    {
        $type = 'StockTransactionMail';
        $namespace = 'Product';
        $class = StockTransactionMail::class;
        $source = static::getContainer()->createSource(Source::Order, 1);
        $item = static::getContainer()->createItem(5, $source);
        $product = $item->getProduct();
        $stockTransactionResult = static::getContainer()->createStockTransactionResult(__METHOD__ . "($name)");
        $stockTransactionResult->setSendStatus($stockTransactionResult::Sent_New);

        $oldDebug = $this->getConfig()->set('debug', $debug);
        try {
            $this->examples->setOptions([Fld::TestMode => $debug === Config::Send_TestMode ? Api::TestMode_Test : Api::TestMode_Normal]);
            $apiResult = $this->getAcumulusResult($uri);
            $apiResult->setMainAcumulusResponseKey($this->examples->getMainResponseKey($uri), $this->examples->isList($uri));
            $stockTransactionResult->setAcumulusResult($apiResult);
            $args = [
                'source' => $item->getSource(),
                'item' => $item,
                'product' => $product,
                'change' => $change,
                'result' => $stockTransactionResult,
            ];
            $this->createAndSendMail($type, $namespace, $class, $args, $name);
        } finally {
            $this->getConfig()->set('debug', $oldDebug);
        }
    }

    /**
     * Tests the mail creation process.
     */
    public function createAndSendMail(string $type, string $namespace, string $class, array $args, string $description = ''): void
    {
        $count = $this->getMailer()->getMailCount();
        $mail = $this->getMail($type, $namespace);
        /** @noinspection UnnecessaryAssertionInspection  testing for a subclass of Mail */
        static::assertInstanceOf($class, $mail);

        $mail->createAndSend($args);
        static::assertSame($count + 1, $this->getMailer()->getMailCount());
        $mailSent = $this->getMailer()->getMailSent($count);
        static::assertIsArray($mailSent);

        $name = "$type-";
        if (!empty($description)) {
            $name .= "$description-";
        }
        $name .= static::getContainer()->getLanguage();
        self::assertMailMatches($name, $mailSent);
    }
}
