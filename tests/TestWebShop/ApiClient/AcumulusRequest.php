<?php
namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\AcumulusRequest as BaseAcumulusRequest;
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\Helpers\Severity;

/**
 * Communicator implements the communication with the Acumulus web API.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - Communicating with the Acumulus webservice using the
 *   {@se HttpCommunicator}.
 * - Good error handling, including detecting html responses from the proxy
 *   before the actual web service.
 */
class AcumulusRequest extends BaseAcumulusRequest
{
    /**
     * @inheritDoc
     */
    public function constructUri(string $apiFunction): string
    {
        return $apiFunction;
    }

    /**
     * @inheritDoc
     */
    public function execute(string $apiFunction, array $message, bool $needContract, ?Result $result = null): Result
    {
        // Add messages for the parameters that were passed in, so they can be checked.
        $result->addMessage($apiFunction, Severity::Log, 'apiFunction', 0);
        $result->addMessage(json_encode($message), Severity::Log, 'message', 0);
        $result->addMessage($needContract ? 'true' : 'false', Severity::Log, 'needContract', 0);
        return $result;
    }
}
