<?php
namespace Siel\Acumulus\ApiClient;

use DOMDocument;
use DOMElement;
use DOMException;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Log;

/**
 * AcumulusRequest turns a call to {@see \Siel\Acumulus\ApiClient\Acumulus} into
 * an {@see \Siel\Acumulus\ApiClient\HttpRequest}.
 *
 * It offers:
 * - Adding the basic submit structure - contract, connector, testmode, ... - to
 *   create a complete request structure.
 * - Conversion from the request structure array to XML.
 * - Creating the full uri for the api function to call.
 * - Sending the request.
 * - Creating the {@see \Siel\Acumulus\ApiClient\Result} from the
 *   {@see \Siel\Acumulus\ApiClient\HttpResponse}.
 * - Good error handling, including:
 *     - Detecting HTML responses from the proxy before the actual web service.
 *     - Detecting XML responses when an error occurred before the <format> was
 *       interpreted.
 *     - Interpreting the HTTP result status code.
 */
class AcumulusRequest
{
    protected /*Config*/ $config;
    protected /*Log*/ $log;
    protected /*string*/ $userLanguage;

    protected /*string*/ $apiFunction = '';
    protected /*array*/ $submitMessage = [];
    protected /*?HttpRequest*/ $httpRequest = null;

    public function __construct(Config $config, string $userLanguage, Log $log)
    {
        $this->config = $config;
        $this->userLanguage = $userLanguage;
        $this->log = $log;
    }

    public function getApiFunction(): string
    {
        return $this->apiFunction;
    }

    public function getSubmitMessage(): array
    {
        return $this->submitMessage;
    }

    public function getHttpRequest(): ?HttpRequest
    {
        return $this->httpRequest;
    }

    /**
     * Constructs and returns the uri for the requested API call.
     *
     * This method is public because {@see \Siel\Acumulus\ApiClient\Acumulus}
     * calls it to get just the uri towards pdf files, thus without executing
     * the request to get that file.
     *
     * @param string $apiFunction
     *   The api service to get the uri for.
     *
     * @return string
     *   The uri to the requested API call.
     */
    public function constructUri(string $apiFunction): string
    {
        $environment = $this->config->getEnvironment();
        return $environment['baseUri'] . '/' . $environment['apiVersion'] . '/' . $apiFunction . '.php';
    }

    /**
     * Sends a message to the given API function and returns the results.
     *
     * Any errors (or warnings) in the response structure of the web service are
     * returned via the result value and should be handled at a higher level.
     *
     * @param string $apiFunction
     *   The API function to invoke.
     * @param array $message
     *   The main submit part to send to the Acumulus web API.
     * @param bool $needContract
     *   Indicates whether this api function needs the contract details. Most
     *   API functions do, but for some general listing functions, like vat
     *   info, it is optional, and for signUp it is even not allowed.
     * @param \Siel\Acumulus\ApiClient\Result|null $result
     *   It is possible to already create a Result object before calling the
     *   api-client to store local messages. By passing this Result object these
     *   local messages will be merged with any remote messages in the returned
     *   Result object.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the web service call. See
     *   {@see https://www.siel.nl/acumulus/API/Basic_Response/} for the
     *   structure of a response. In case of errors, an exception or error
     *   message will have been added to the Result and the main response may be
     *   empty.
     */
    public function execute(string $apiFunction, array $message, bool $needContract, ?Result $result = null): Result
    {
        if ($result === null) {
            $result = new Result();
        }

        $result->setAcumulusRequest($this);
        $this->apiFunction = $apiFunction;
        try {

            $uri = $this->constructUri($this->apiFunction);
            $body = $this->constructSubmitMessage($message, $needContract);
            // Send message, receive response.
            $this->httpRequest = new HttpRequest();
            $httpResponse = $this->httpRequest->post($uri, $body);
            $result->setHttpResponse($httpResponse);
        } catch (RuntimeException $e) {
            // Any errors during:
            // - conversion of the message to xml
            // - communication with the Acumulus web service
            // - converting the answer to an array
            // are returned as a RuntimeException.
            $result->addMessage($e);
        }

        return $result;
    }

    /**
     * Constructs the submit message to be sent to the Acumulus API.
     *
     * We use the {@link https://www.siel.nl/acumulus/API/Basic_Usage/#:~:text=The%20xmlstring%20approach [XML string approach]}
     * to construct the submit-message. That is we send the message in the body
     * of a POST request in the multipart/form-data format. By passing an array
     * to Curl, we let Curl do the formatting and encoding.
     *
     * A submit-message is an XML message consisting of:
     * - A {@link https://www.siel.nl/acumulus/API/Basic_Submit/ [basic submit]}
     *   part containing a.o. tags like <contract>, <testmode>, and <connector>.
     * - An endpoint specific part, the actual data to be sent.
     *
     * @param array $message
     *   The endpoint specific part to be sent.
     * @param bool $needContract
     *   Whether this endpoint needs the <contract> part to authorize and
     *   authenticate the call.
     *
     * @return array
     *   The XML string to send to Acumulus.
     */
    protected function constructSubmitMessage(array $message, bool $needContract): array
    {
        $commonMessagePart = $this->getBasicSubmit($needContract);
        $this->submitMessage = array_merge($commonMessagePart, $message);

        // Convert message to XML. XML requires 1 top level tag, so add one.
        // The top tag name is ignored by the Acumulus API (we use <acumulus>).
        return ['xmlstring' => trim($this->convertArrayToXml(['acumulus' => $this->submitMessage]))];
    }

    /**
     * Returns the common part of each API message.
     *
     * The common part consists of the following tags:
     * - contract (optional).
     * - format: 'json' or 'xml'.
     * - testmode: 0 (real) or 1 (test mode).
     * - lang: Language for error and warning in responses.
     * - inodes: List of ";"-separated XML-node identifiers which should be
     *     included in the response. Defaults to full response when left out or
     *     empty.
     * - connector: information about the client.
     *
     * @param bool $needContract
     *   Indicates whether this api function needs the contract details. Most
     *   API functions do, so the default is true, but for some general listing
     *   functions, like vat info, it is optional, and for sign-up it is even
     *   not allowed.
     *
     * @return array
     *   The common part of an API message.
     *
     * @see https://www.siel.nl/acumulus/API/Basic_Submit/
     */
    protected function getBasicSubmit(bool $needContract): array
    {
        $environment = $this->config->getEnvironment();
        $pluginSettings = $this->config->getPluginSettings();

        $result = [];
        if ($needContract) {
            $result['contract'] = $this->config->getCredentials();
        }
        $result += [
            'format' => $pluginSettings['outputFormat'],
            'testmode' => $pluginSettings['debug'] === Config::Send_TestMode ? Api::TestMode_Test : Api::TestMode_Normal,
            'lang' => $this->userLanguage,
            'connector' => [
                'application' => "{$environment['shopName']} {$environment['shopVersion']}",
                'webkoppel' => "Acumulus {$environment['moduleVersion']}",
                'development' => 'SIEL - Buro RaDer',
                'remark' => "Library {$environment['libraryVersion']} - PHP {$environment['phpVersion']}",
                'sourceuri' => 'https://github.com/SIELOnline/libAcumulus',
            ],
        ];
        return $result;
    }

    /**
     * Converts a keyed, optionally multi-level, array to XML.
     *
     * Each key is converted to a tag, no attributes are used. Numeric
     * sub-arrays are repeated using the same key.
     *
     * @param array $values
     *   The array to convert to XML.
     *
     * @return string
     *   The XML string
     *
     * @throws \RuntimeException
     */
    protected function convertArrayToXml(array $values): string
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->xmlStandalone = true;
        $dom->formatOutput = true;

        try {
            $dom = $this->convertToDom($values, $dom);
            $result = $dom->saveXML();
            if (!$result) {
                throw new RuntimeException('DOMDocument::saveXML failed');
            }
            // Backslashes get lost between here and the Acumulus API, but
            // encoding them makes them get through. Solve here until the
            // real error has been found and solved.
            /** @noinspection PhpUnnecessaryLocalVariableInspection */
            $result = str_replace('\\', '&#92;', $result);
            return $result;
        } catch (DOMException $e) {
            // Convert a DOMException to a RuntimeException, so we only have to
            // handle RuntimeExceptions.
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Recursively converts a value to a DOMDocument|DOMElement.
     *
     * @param mixed $values
     *   A keyed array, a numerically indexed array, or a scalar type.
     * @param DOMDocument|DOMElement $element
     *   The element to append the values to.
     *
     * @return DOMDocument|DOMElement
     *
     * @throws \DOMException
     */
    protected function convertToDom($values, $element)
    {
        /** @var DOMDocument $document */
        static $document = null;
        $isFirstElement = true;

        if ($element instanceof DOMDocument) {
            $document = $element;
        }
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                if (is_int($key)) {
                    if ($isFirstElement) {
                        $node = $element;
                        $isFirstElement = false;
                    } else {
                        $node = $document->createElement($element->tagName);
                        $element->parentNode->appendChild($node);
                    }
                } else {
                    $node = $document->createElement($key);
                    $element->appendChild($node);
                }
                $this->convertToDom($value, $node);
            }
        } else {
            $element->appendChild($document->createTextNode(is_bool($values) ? ($values ? 'true' : 'false') : $values));
        }

        return $element;
    }
}
