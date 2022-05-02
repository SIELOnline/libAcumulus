<?php
/**
 * @noinspection DuplicatedCode
 * @noinspection PhpStaticAsDynamicMethodCallInspection
 */

namespace Siel\Acumulus\ApiBehaviour\ApiClient;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\ApiClient\AcumulusException;
use Siel\Acumulus\ApiClient\AcumulusResponseException;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Severity;

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
    protected const ValidToken = 'JrJ8bS0aTBxFOLn7ClNBYBHNdMJR8d96';
    protected const InvalidToken = 'JrJ8bS0aTBxFOLn7ClNBYBHNdMJR8d97';

    protected /*Container*/ $container;
    protected /*Environment*/ $environment;
    /** @var \Siel\Acumulus\TestWebShop\ApiClient\Acumulus  */
    protected /*Acumulus*/ $acumulusClient;
    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    protected function setUp(): void
    {
        // Using TestWebShop would give us test classes, but we want real ones
        // here.
        $this->container = new Container('TestWebShop', 'nl');
        $this->environment = $this->container->getEnvironment();
        $this->log = $this->container->getLog();
        $this->acumulusClient = $this->container->getAcumulusApiClient();
    }

    /**
     * Test the reaction on a request for a non-existing uri.
     */
    public function testTimeout()
    {
        try {
            $this->acumulusClient->timeout();
            $this->fail('Should not arrive here');
        } catch (AcumulusException $e) {
            $this->assertCount(2, $this->log->getLoggedMessages());
            $this->assertSubmittedRequestHasBeenLogged(0, Severity::Exception);
            $loggedMessages = $this->log->getLoggedMessages();
            $loggedMessage2 = end($loggedMessages);
            $lastMessage = $loggedMessage2['message'];
            $lastSeverity = $loggedMessage2['severity'];
            $this->assertStringStartsWith('AcumulusException: '. CURLE_OPERATION_TIMEDOUT . ': curl_exec()', $lastMessage);
            $this->assertStringContainsString(CURLE_OPERATION_TIMEDOUT, $lastMessage);
            $this->assertEquals(Severity::Exception, $lastSeverity);
        }
    }

    /**
     * Test the reaction on a request for a non-existing uri.
     */
    public function test404()
    {
        try {
            $this->acumulusClient->notExisting();
            $this->fail('Should not arrive here');
        } catch (AcumulusResponseException $e) {
            $this->assertCount(2, $this->log->getLoggedMessages());
            $this->assertSubmittedRequestHasBeenLogged(0, Severity::Exception);
            $this->assertHttpLevelErrorHasBeenLogged(-1, 404, 'Not Found', Severity::Exception);
        }
    }

    /**
     * Test the reaction on a request for a non-existing uri.
     */
    public function test403()
    {
        try {
            $this->acumulusClient->noContract();
            $this->fail('Should not arrive here');
        } catch (AcumulusResponseException $e) {
            $this->assertCount(2, $this->log->getLoggedMessages());
            $this->assertSubmittedRequestHasBeenLogged(0, Severity::Exception);
            $this->assertHttpLevelErrorHasBeenLogged(-1, 403, '"error":{"code":"403', Severity::Exception);
        }
    }

    public function testEntryNotfound()
    {
        $result = $this->acumulusClient->getEntry(1);
        $this->assertTrue($result->hasError());
        $this->assertCount(2, $this->log->getLoggedMessages());
        $this->assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        $this->assertApplicationLevelErrorHasBeenLogged(-1, 404,  Severity::Error);
    }

    public function testSetDeleteStatusEntryNotfound()
    {
        $result = $this->acumulusClient->setDeleteStatus(1, Api::Entry_Delete);
        $this->assertTrue($result->hasError());
        $this->assertCount(2, $this->log->getLoggedMessages());
        $this->assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        $this->assertApplicationLevelErrorHasBeenLogged(-1, 404,  Severity::Error);
    }

    public function testConceptNotFound()
    {
        //  valid ConceptId: 171866
        $result = $this->acumulusClient->getConceptInfo(123);
        $this->assertTrue($result->hasError());
        $this->assertCount(2, $this->log->getLoggedMessages());
        $this->assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        $this->assertApplicationLevelErrorHasBeenLogged(-1, 400,  Severity::Error);
    }

    public function testSetPaymentStatusInvalidToken()
    {
        $result = $this->acumulusClient->setPaymentStatus(static::InvalidToken, Api::PaymentStatus_Paid);
        $this->assertTrue($result->hasError());
        $this->assertCount(2, $this->log->getLoggedMessages());
        $this->assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        $this->assertApplicationLevelErrorHasBeenLogged(-1, 400,  Severity::Error);
    }

    public function testEmailAsPdfInvalidToken()
    {
        $result = $this->acumulusClient->emailInvoiceAsPdf(static::InvalidToken, ['emailto' => 'unit.test@burorader.com']);
        $this->assertTrue($result->hasError());
        $this->assertCount(2, $this->log->getLoggedMessages());
        $this->assertSubmittedRequestHasBeenLogged(0, Severity::Error);
        $this->assertApplicationLevelErrorHasBeenLogged(-1, 400,  Severity::Error);
    }

    public function testEmptyPicklistProducts()
    {
        $result = $this->acumulusClient->getPicklistProducts('This will not match any product');
        $this->assertFalse($result->hasError());
        $this->assertCount(0, $result->getMainAcumulusResponse());
        $this->assertCount(2, $this->log->getLoggedMessages());
        $this->assertSubmittedRequestHasBeenLogged(0, Severity::Success);
        $this->assertApplicationLevelSuccessHasBeenLogged(-1);
    }

    public function testEmptyPicklistDiscountProfiles()
    {
        $result = $this->acumulusClient->getPicklistDiscountProfiles();
        $this->assertFalse($result->hasError());
        $this->assertCount(0, $result->getMainAcumulusResponse());
        $this->assertCount(2, $this->log->getLoggedMessages());
        $this->assertSubmittedRequestHasBeenLogged(0, Severity::Success);
        $this->assertApplicationLevelSuccessHasBeenLogged(-1);
    }

    public function assertSubmittedRequestHasBeenLogged(int $index, int $expectedSeverity): void
    {
        $loggedMessages = $this->log->getLoggedMessages();
        $index = $index >=0 ? $index : count($loggedMessages) - $index;
        $loggedMessage = $loggedMessages[$index];
        $message = $loggedMessage['message'];
        $severity = $loggedMessage['severity'];
        $this->assertStringStartsWith('Request: ', $message);
        $this->assertStringContainsString('uri="', $message);
        $this->assertStringContainsString('submit={', $message);
        $this->assertEquals($expectedSeverity, $severity);
    }

    public function assertHttpLevelErrorHasBeenLogged(
        int $index,
        int $expectedCode,
        string $expectedPhrase,
        int $expectedSeverity
    ): void {
        $loggedMessages = $this->log->getLoggedMessages();
        $index = $index >=0 ? $index : (count($loggedMessages) + $index);
        $loggedMessage = $loggedMessages[$index];
        $message = $loggedMessage['message'];
        $severity = $loggedMessage['severity'];
        $this->assertStringContainsString("HTTP status code=$expectedCode", $message);
        $this->assertStringContainsString($expectedPhrase, $message);
        $this->assertEquals($expectedSeverity, $severity);
    }

    public function assertApplicationLevelErrorHasBeenLogged(int $index, int $expectedCode, int $expectedSeverity):void
    {
        $loggedMessages = $this->log->getLoggedMessages();
        $index = $index >=0 ? $index : (count($loggedMessages) + $index);
        $loggedMessage = $loggedMessages[$index];
        $message = $loggedMessage['message'];
        $severity = $loggedMessage['severity'];
        $this->assertStringStartsWith("Response: status=$expectedCode", $message);
        $this->assertStringContainsString("\"error\":{\"code\":\"$expectedCode ", $message);
        $this->assertEquals($expectedSeverity, $severity);
    }

    public function assertApplicationLevelSuccessHasBeenLogged(int $index):void
    {
        $loggedMessages = $this->log->getLoggedMessages();
        $index = $index >=0 ? $index : (count($loggedMessages) + $index);
        $loggedMessage = $loggedMessages[$index];
        $message = $loggedMessage['message'];
        $severity = $loggedMessage['severity'];
        $this->assertStringContainsString('Response: status=200', $message);
        $this->assertStringContainsString('"status":"0"', $message);
        $this->assertEquals(Severity::Success, $severity);
    }
}
