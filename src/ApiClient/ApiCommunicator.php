<?php
namespace Siel\Acumulus\ApiClient;

use DOMDocument;
use DOMElement;
use DOMException;
use LibXMLError;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Severity;

/**
 * Communicator implements the communication with the Acumulus WebAPI.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - Communicating with the Acumulus webservice using the
 *   {@see \Siel\Acumulus\ApiClient\HttpRequest}.
 * - Good error handling, including detecting html responses from the proxy
 *   before the actual web service.
 */
class ApiCommunicator
{
    protected ConnectionHandler $connectionHandler;
    protected Config $config;
    protected Log $log;
    protected string $userLanguage;

    public function __construct(ConnectionHandler $connectionHandler, Config $config, string $userLanguage, Log $log)
    {
        $this->connectionHandler = $connectionHandler;
        $this->config = $config;
        $this->userLanguage = $userLanguage;
        $this->log = $log;
    }

    /**
     * Constructs and returns the uri for the requested API call.
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
     * @param string $apiFunction
     *   The API function to invoke.
     * @param array $message
     *   The values to submit.
     * @param bool $needContract
     *   Indicates whether this api function needs the contract details. Most
     *   API functions do, do the default is true, but for some general listing
     *   functions, like vat info, it is optional, and for signUp, it is even
     *   not allowed.
     * @param \Siel\Acumulus\ApiClient\Result $result
     *   It is possible to already create a Result object before calling the
     *   api-client to store local messages. By passing this Result object these
     *   local messages will be merged with any remote messages in the returned
     *   Result object.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   A Result object containing the results.
     */
    public function callApiFunction(string $apiFunction, array $message, bool $needContract, Result $result): Result
    {
        $uri = $this->constructUri($apiFunction);
        try {
            $commonMessagePart = $this->getBasicSubmit($needContract);
            $message = array_merge($commonMessagePart, $message);

            // Send message, receive response.
            $result = $this->sendApiMessage($uri, $message, $result);
        } catch (RuntimeException $e) {
            $result->addMessage($e);
            $this->connectionHandler->close($this->connectionHandler->get($uri));
        }

        $message = sprintf(
            "ApiCommunicator::callApiFunction() uri=%s\nrequest=%s",
            $uri,
            $this->maskPasswords($result->getHttpRequest()->getPostFieldsAsMsg())
        );
        if ($result->getHttpResponse() !== null) {
            $message .= sprintf(
                "\ncode=%d\nresponse=%s",
                $result->getHttpResponse()->getHttpCode(),
                $this->maskPasswords($result->getHttpResponse()->getBody()),
            );
        }
        $this->log->debug($message);
        return $result;
    }

    /**
     * Returns the common part of each API message.
     *
     * The common part consists of the following tags:
     * - contract (optional)
     * - format: 'json' or 'xml'
     * - testmode: 0 (real) or 1 (test mode)
     * - lang: Language for error and warning in responses.
     * - inodes: List of ";"-separated XML-node identifiers which should be
     *     included in the response. Defaults to full response when left out or
     *     empty.
     * - connector: information about the client
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
     * Sends a message to the Acumulus API and returns the answer.
     *
     * Any errors during:
     * - conversion of the message to xml,
     * - communication with the Acumulus web service
     * - converting the answer to an array
     * are returned as a RuntimeException.
     *
     * Any errors (or warnings) in the response structure of the web service are
     * returned via the result value and should be handled at a higher level.
     *
     * @param string $uri
     *   The URI of the Acumulus WebAPI call to send the message to.
     * @param array $message
     *   The message to send to the Acumulus WebAPI.
     * @param \Siel\Acumulus\ApiClient\Result $result
     *   The result object to add the results to.
     *
     * @return \Siel\Acumulus\ApiClient\Result
     *   The result of the web service call.
     *   See {@see https://www.siel.nl/acumulus/API/Basic_Response/}
     *   for the structure of a response.
     *
     * @throws \RuntimeException
     */
    protected function sendApiMessage(string $uri, array $message, Result $result): Result
    {
        // Convert message to XML. XML requires 1 top level tag, so add one.
        // The top tag name is ignored by the Acumulus WebAPI.
        $message = trim($this->convertArrayToXml(['acumulus' => $message]));
        $httpRequest = new HttpRequest($this->connectionHandler->get($uri));
        $result->setHttpRequest($httpRequest);
        $httpResponse = $httpRequest->post($uri, ['xmlstring' => $message])->execute();
        $result->setHttpResponse($httpResponse);

        // @todo: response knowledge can be placed in Result (move "parsing"
        //   body into setResponse, called internally from setHttpResponse.
        // @todo: start using http codes (404, 429, 500, ...)
        $body = $httpResponse->getBody();
        if (empty($body)) {
            // CURL may get a time-out and return an empty response without
            // further error messages: Add an error to tell the user to check if
            // the invoice was sent or not.
            $result->addMessage('Empty response body', Severity::Error, '', 701);
        } elseif ($this->isHtmlResponse($body)) {
            // When the API is gone we might receive an HTML error message page.
            $this->raiseHtmlReceivedError($body);
        } else {
            // Decode the response as either json or xml.
            $response = [];
            $pluginSettings = $this->config->getPluginSettings();

            if ($pluginSettings['outputFormat'] === 'json') {
                $response = json_decode($body, true);
            }
            // Even if we pass <format>json</format> we might receive an XML
            // response in case the XML was rejected before or during parsing.
            // So if the response is null we also try to decode the response as
            // XML.
            if ($pluginSettings['outputFormat'] === 'xml' || !is_array($response)) {
                try {
                    $response = $this->convertXmlToArray($body);
                } catch (RuntimeException $e) {
                    // Not an XML response. Treat it as a json error if we were
                    // expecting a json response.
                    if ($pluginSettings['outputFormat'] === 'json') {
                        $this->raiseJsonError();
                    }
                    // Otherwise, treat it as the XML exception that was raised.
                    throw $e;
                }
            }
            $result->setResponse($response);
        }

        return $result;
    }

    /**
     * @param string $response
     *
     * @return bool
     *   True if the response is html, false otherwise.
     */
    protected function isHtmlResponse(string $response): bool
    {
        return strtolower(substr($response, 0, strlen('<!doctype html'))) === '<!doctype html'
            || strtolower(substr($response, 0, strlen('<html'))) === '<html'
            || strtolower(substr($response, 0, strlen('<body'))) === '<body';
    }

    /**
     * Mask passwords in Acumulus messages.
     *
     * Masking passwords allows to safely log communication between the Acumulus
     * client and server.
     *
     * @param string $msg
     *   A string that contains an Acumulus request or response message. These
     *   messages are either in XML or in Json format. Password fields are
     *   supposed to end with the string "password", but may have a prefix.
     *
     * @return string
     *   The message with the value of password fields replaced with the string
     *   "REMOVED FOR SECURITY"
     */
    protected function maskPasswords(string $msg): string
    {
        // Xml: don't be greedy to prevent replacing multiple instances at once.
        $masked = preg_replace('|<([-_.a-z0-9]*)password>.*</[-_.a-z0-9]*password>|U','<$1password>REMOVED FOR SECURITY</$1password>', $msg);

        // Json: getting the right number of back-slashes is not easy...
        return preg_replace('/"([-_.a-z0-9]*)password"(\\s*):(\\s*)"([^\\\\"]|(\\\\.))*"/', '"$1password"$2:$3"REMOVED FOR SECURITY"', $masked);
    }

    /**
     * Converts an XML string to an array.
     *
     * @param string $xml
     *   A string containing XML.
     *
     * @return array
     *  An array representation of the XML string.
     *
     * @throws \RuntimeException
     */
    protected function convertXmlToArray(string $xml): array
    {
        // Convert the response to an array via a 3-way conversion:
        // - create a simplexml object
        // - convert that to json
        // - convert json to array
        libxml_use_internal_errors(true);
        if (!($result = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA))) {
            $this->raiseLibxmlError(libxml_get_errors());
        }

        if (!($result = json_encode($result))) {
            $this->raiseJsonError();
        }
        if (($result = json_decode($result, true)) === null) {
            $this->raiseJsonError();
        }

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

    /**
     * Adds a libxml error messages to the result.
     *
     * @param LibXMLError[] $errors
     *
     * @throws \RuntimeException
     */
    protected function raiseLibxmlError(array $errors)
    {
        $messages = [];
        $code = 704;
        foreach ($errors as $error) {
            // Overwrite our own code with the 1st code we get from libxml.
            if ($code === 704) {
                $code = $error->code;
            }
            $messages[] = sprintf('Line %d, column: %d: %s %d - %s', $error->line, $error->column, $error->level === LIBXML_ERR_WARNING ? 'warning' : 'error', $error->code, trim($error->message));
        }
        throw new RuntimeException(implode("\n", $messages), $code);
    }

    /**
     * Adds a json error message to the result.
     *
     * @throws \RuntimeException
     */
    protected function raiseJsonError()
    {
        $code = json_last_error();
        switch ($code) {
            case JSON_ERROR_NONE:
                $message = 'No error';
                break;
            case JSON_ERROR_DEPTH:
                $message = 'Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = 'Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = 'Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $message = 'Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $message = 'Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $code = 705;
                $message = 'Unknown error';
                break;
        }
        $env = $this->config->getEnvironment();
        $message = sprintf('json (%s): %d - %s', $env['jsonVersion'], $code, $message);
        throw new RuntimeException($message, $code);
    }

    /**
     * Returns an error message containing the received HTML.
     *
     * @param string $response
     *   String containing an HTML document.
     *
     * @trows \RuntimeException
     */
    protected function raiseHtmlReceivedError(string $response)
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->loadHTML($response);
        $body = $doc->getElementsByTagName('body');
        if ($body->length > 0) {
            $body = $body->item(0)->textContent;
        } else {
            $body = '';
        }
        throw new RuntimeException("HTML response received: $body", 702);
    }
}
