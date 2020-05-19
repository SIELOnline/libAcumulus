<?php
namespace Siel\Acumulus\TestWebShop\ApiClient;

use Siel\Acumulus\ApiClient\ApiCommunicator as BaseApiCommunicator;
use Siel\Acumulus\ApiClient\Result;
use Siel\Acumulus\Helpers\Severity;

/**
 * Communicator implements the communication with the Acumulus WebAPI.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - Communicating with the Acumulus webservice using the
 *   {@se HttpCommunicator}.
 * - Good error handling, including detecting html responses from the proxy
 *   before the actual web service.
 */
class ApiCommunicator extends BaseApiCommunicator
{
    /**
     * @inheritDoc
     */
    public function getUri($apiFunction)
    {
        return $apiFunction;
    }

    /**
     * @inheritDoc
     */
    public function callApiFunction($apiFunction, array $message, $needContract = true, Result $result = null)
    {
        if ($result === null) {
            $result = $this->container->getResult();
        }
        $result->addMessage($apiFunction, Severity::Log, 'apiFunction', 0);
        $result->addMessage(json_encode($message), Severity::Log, 'message', 0);
        $result->addMessage($needContract ? 'true' : 'false', Severity::Log, 'needContract', 0);
        return $result;
    }
}
