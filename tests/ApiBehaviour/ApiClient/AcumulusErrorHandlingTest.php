<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\ApiBehaviour\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\Acumulus;
use Siel\Acumulus\ApiClient\AcumulusException;
use Siel\Acumulus\ApiClient\AcumulusResponseException;
use Siel\Acumulus\ApiClient\HttpRequest;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Data\EmailInvoiceAsPdf;
use Siel\Acumulus\Data\StockTransaction;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Tests\AcumulusTestUtils;

use function count;

/**
 * This class tests the actual behaviour of the Acumulus API Server in case of
 * errors.
 *
 * This library attempts to react to all possible types of errors that
 * can occur when communicating with the API server. For severe errors,
 * exceptions, it is important that all information that may help in researching
 * the error is logged. So this text class forces a number of error situations
 * and checks that the:
 * - Library acts as designed, throwing the right exception type
 * - Exceptions contains the message and code as intended
 * - Exceptions and underlying communications are logged.
 */
class AcumulusErrorHandlingTest extends TestCase
{
    use AcumulusTestUtils;

    protected const ValidToken = 'JrJ8bS0aTBxFOLn7ClNBYBHNdMJR8d96';
    protected const InvalidToken = 'JrJ8bS0aTBxFOLn7ClNBYBHNdMJR8d97';
    protected const ValidEntryId = 45967305;
    protected const InvalidEntryId = 1;

    protected Environment $environment;
    /** @var \Siel\Acumulus\TestWebShop\ApiClient\Acumulus  */
    protected Acumulus $acumulusClient;

    protected function setUp(): void
    {
        // Using TestWebShop would give us test classes, but we want real ones
        // here.
        $this->environment = self::getContainer()->getEnvironment();
        $this->acumulusClient = self::getContainer()->getAcumulusApiClient();
        // Clear already logged messages...
        (function () {
            /** @noinspection PhpDynamicFieldDeclarationInspection */
            $this->loggedMessages = [];
        })->call(self::getContainer()->getLog());
    }

    /**
     * Test the reaction on a request for a non-existing uri.
     */
    public function testTimeout(): void
    {
        // With a version change, automated testing might fail as 3 messages get
        // logged.
        $offset = count(self::getLog()->getLoggedMessages());
        try {
            $this->acumulusClient->timeout(static::ValidEntryId);
            $this->fail('Should not arrive here');
        } catch (AcumulusException) {
            static::assertCount(2 + $offset, self::getLog()->getLoggedMessages());
            static::assertSubmittedRequestHasBeenLogged(0, Severity::Exception);
            $loggedMessages = self::getLog()->getLoggedMessages();
            $loggedMessage2 = end($loggedMessages);
            $lastMessage = $loggedMessage2['message'];
            $lastSeverity = $loggedMessage2['severity'];
            static::assertStringStartsWith('AcumulusException: curl_exec()', $lastMessage);
            static::assertStringContainsString((string) CURLE_OPERATION_TIMEDOUT, $lastMessage);
            static::assertEquals(Severity::Exception, $lastSeverity);
        }
    }

    /**
     * Test the reaction on a request for a non-existing uri.
     */
    public function test404(): void
    {
        try {
            $this->acumulusClient->notExisting();
            $this->fail('Should not arrive here');
        } catch (AcumulusResponseException) {
            static::assertCount(2, self::getLog()->getLoggedMessages());
            static::assertSubmittedRequestHasBeenLogged(0, Severity::Exception);
            static::assertHttpLevelErrorHasBeenLogged(-1, 404, 'Not Found', Severity::Exception);
        }
    }

    /**
     * Test the reaction on a request for a non-existing uri.
     */
    public function test403(): void
    {
        $result = $this->acumulusClient->noContract();
        static::assertTrue($result->hasError());
        static::assertNotNull($result->getByCode(403));
        static::assertSame(403, $result->getHttpResponse()->getHttpStatusCode());
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        static::assertApplicationLevelErrorHasBeenLogged(-1, 403,  Severity::Error);
    }

    /**
     * Test the reaction on a request with a non-complete 'contract' section.
     */
    public function testNoEmailOnError(): void
    {
        $result = $this->acumulusClient->noEmailOnError(static::ValidEntryId);
        static::assertFalse($result->hasError());
        static::assertSame(200, $result->getHttpResponse()->getHttpStatusCode());
        static::assertArrayHasKey('entryid', $result->getMainAcumulusResponse());
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Success);
        static::assertApplicationLevelSuccessHasBeenLogged(-1);
    }

    /**
     * Test the reaction on a request with a non-complete 'contract' section.
     */
    public function testNoEmailOnWarning(): void
    {
        $result = $this->acumulusClient->noEmailOnWarning(static::ValidEntryId);
        static::assertFalse($result->hasError());
        static::assertSame(200, $result->getHttpResponse()->getHttpStatusCode());
        static::assertArrayHasKey('entryid', $result->getMainAcumulusResponse());
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Success);
        static::assertApplicationLevelSuccessHasBeenLogged(-1);
    }

    public function testEntryNotfound(): void
    {
        $result = $this->acumulusClient->getEntry(1);
        static::assertTrue($result->hasError());
        static::assertSame(404, $result->getHttpResponse()->getHttpStatusCode());
        static::assertNotNull($result->getByCode(404));
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        static::assertApplicationLevelErrorHasBeenLogged(-1, 404,  Severity::Error);
    }

    public function testSetDeleteStatusEntryNotfound(): void
    {
        $result = $this->acumulusClient->setDeleteStatus(1, Api::Entry_Delete);
        static::assertTrue($result->hasError());
        static::assertSame(404, $result->getHttpResponse()->getHttpStatusCode());
        static::assertNotNull($result->getByCode(404));
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        static::assertApplicationLevelErrorHasBeenLogged(-1, 404,  Severity::Error);
    }

    public function testConceptNotFound(): void
    {
        //  valid ConceptId: 171866
        $result = $this->acumulusClient->getConceptInfo(123);
        static::assertTrue($result->hasError());
        static::assertSame(400, $result->getHttpResponse()->getHttpStatusCode());
        static::assertNotNull($result->getByCode(400));
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        static::assertApplicationLevelErrorHasBeenLogged(-1, 400,  Severity::Error);
    }

    public function testSetPaymentStatusInvalidToken(): void
    {
        $result = $this->acumulusClient->setPaymentStatus(static::InvalidToken, Api::PaymentStatus_Paid);
        static::assertTrue($result->hasError());
        static::assertSame(400, $result->getHttpResponse()->getHttpStatusCode());
        static::assertNotNull($result->getByCode(400));
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        static::assertApplicationLevelErrorHasBeenLogged(-1, 400,  Severity::Error);
    }

    public function testGetInvoicePdfInvalidToken(): void
    {
        $uri = $this->acumulusClient->getInvoicePdfUri(static::InvalidToken);
        $httpRequest = new HttpRequest([CURLOPT_HTTPHEADER => ['Cache-Control: no-cache']]);
        $response = $httpRequest->get($uri);

        static::assertSame(400, $response->getHttpStatusCode());
        static::assertStringContainsString('AA69CBAA', $response->getBody());
    }

    public function testEmailAsPdfInvalidToken(): void
    {
        $emailInvoiceAsPdf = new EmailInvoiceAsPdf();
        $emailInvoiceAsPdf->setEmailTo('test@example.com');

        $result = $this->acumulusClient->emailInvoiceAsPdf(static::InvalidToken, $emailInvoiceAsPdf);
        static::assertTrue($result->hasError());
        static::assertNotNull($result->getByCode(400));
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        static::assertApplicationLevelErrorHasBeenLogged(-1, 400,  Severity::Error);
    }

    public function testProductNotfound(): void
    {
        $stockTransaction = new StockTransaction();
        $stockTransaction->productId = 1;
        $stockTransaction->stockAmount = 1;

        $result = $this->acumulusClient->stockTransaction($stockTransaction);
        static::assertTrue($result->hasError());
        static::assertSame(404, $result->getHttpResponse()->getHttpStatusCode());
        static::assertNotNull($result->getByCode(404));
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        static::assertApplicationLevelErrorHasBeenLogged(-1, 404,  Severity::Error);
    }

    public function testEmptyPicklistProducts(): void
    {
        $result = $this->acumulusClient->getPicklistProducts('This will not match any product');
        static::assertFalse($result->hasError());
        static::assertSame(200, $result->getHttpResponse()->getHttpStatusCode());
        static::assertCount(0, $result->getMainAcumulusResponse());
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Success);
        static::assertApplicationLevelSuccessHasBeenLogged(-1);
    }

    public function testEmptyPicklistDiscountProfiles(): void
    {
        $result = $this->acumulusClient->getPicklistDiscountProfiles();
        static::assertFalse($result->hasError());
        static::assertSame(200, $result->getHttpResponse()->getHttpStatusCode());
        static::assertCount(0, $result->getMainAcumulusResponse());
        static::assertCount(2, self::getLog()->getLoggedMessages());
        static::assertSubmittedRequestHasBeenLogged(0, Severity::Success);
        static::assertApplicationLevelSuccessHasBeenLogged(-1);
    }

    public static function assertSubmittedRequestHasBeenLogged(int $index, int $expectedSeverity): void
    {
        $loggedMessages = self::getLog()->getLoggedMessages();
        $index = $index >= 0 ? $index : count($loggedMessages) - $index;
        $loggedMessage = $loggedMessages[$index];
        $message = $loggedMessage['message'];
        $severity = $loggedMessage['severity'];
        static::assertStringStartsWith('Request: uri=', $message);
        static::assertStringContainsString('submit={', $message);
        static::assertEquals($expectedSeverity, $severity);
    }

    public static function assertHttpLevelErrorHasBeenLogged(
        int $index,
        int $expectedCode,
        string $expectedPhrase,
        int $expectedSeverity
    ): void {
        $loggedMessages = self::getLog()->getLoggedMessages();
        $index = $index >=0 ? $index : (count($loggedMessages) + $index);
        $loggedMessage = $loggedMessages[$index];
        $message = $loggedMessage['message'];
        $severity = $loggedMessage['severity'];
        static::assertStringContainsString("HTTP status code=$expectedCode", $message);
        static::assertStringContainsString($expectedPhrase, $message);
        static::assertEquals($expectedSeverity, $severity);
    }

    public static function assertApplicationLevelErrorHasBeenLogged(int $index, int $expectedCode, int $expectedSeverity):void
    {
        $loggedMessages = self::getLog()->getLoggedMessages();
        $index = $index >=0 ? $index : (count($loggedMessages) + $index);
        $loggedMessage = $loggedMessages[$index];
        $message = str_replace(["\r", "\n", "\t", ' '], '', $loggedMessage['message']);
        $severity = $loggedMessage['severity'];
        static::assertStringStartsWith("Response:status=$expectedCode", $message);
        static::assertStringContainsString("\"error\":{\"code\":\"$expectedCode", $message);
        static::assertEquals($expectedSeverity, $severity);
    }

    public static function assertApplicationLevelSuccessHasBeenLogged(int $index):void
    {
        $loggedMessages = self::getLog()->getLoggedMessages();
        $index = $index >=0 ? $index : (count($loggedMessages) + $index);
        $loggedMessage = $loggedMessages[$index];
        $message = str_replace(["\r", "\n", "\t", ' '], '', $loggedMessage['message']);
        $severity = $loggedMessage['severity'];
        static::assertStringContainsString('Response:status=200', $message);
        static::assertStringContainsString('"status":"0"', $message);
        static::assertEquals(Severity::Success, $severity);
    }
}
