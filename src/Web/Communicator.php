<?php
namespace Siel\Acumulus\Web;

use DOMDocument;
use DOMElement;
use LibXMLError;
use RuntimeException;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Helpers\Container;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\PluginConfig;

/**
 * Communicator implements the communication with the Acumulus WebAPI.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - (https) Communication with the Acumulus webservice using the curl library:
 *   setting up the connection, sending the request, receiving the response.
 * - Good error handling.
 */
class Communicator
{
    /** @var \Siel\Acumulus\Config\Config */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var \Siel\Acumulus\Helpers\Container */
    protected $container;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /**
     * Communicator constructor.
     *
     * @param \Siel\Acumulus\Helpers\Container $container
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Container $container, Config $config, Translator $translator, Log $log)
    {
        $this->container = $container;
        $this->config = $config;
        $this->translator = $translator;
        $this->log = $log;
    }

    /**
     * Returns the uri to the requested API call.
     *
     * @param string $apiFunction
     *
     * @return string
     *   The uri to the requested API call.
     */
    public function getUri($apiFunction)
    {
        $environment = $this->config->getEnvironment();
        $uri = $environment['baseUri'] . '/' . $environment['apiVersion'] . '/' . $apiFunction . '.php';
        return $uri;
    }

    /**
     * Sends a message to the given API function and returns the results.
     *
     * @param string $apiFunction
     *   The API function to invoke.
     * @param array $message
     *   The values to submit.
     * @param \Siel\Acumulus\Web\Result $result
     *   It is possible to already create a Result object before calling the Web
     *   Service to store local messages. By passing this Result object these
     *   local messages will be merged with any remote messages in the returned
     *   Result object.
     *
     * @return \Siel\Acumulus\Web\Result
     *   A Result object containing the results.
     */
    public function callApiFunction($apiFunction, array $message, Result $result = null)
    {
        if ($result === null) {
            $result = $this->container->getResult();
        }
        $uri = $this->getUri($apiFunction);

        try {
            $commonMessagePart = $this->getCommonPart();
            $message = array_merge($commonMessagePart, $message);

            // Send message, receive response.
            $result = $this->sendApiMessage($uri, $message, $result);
        } catch (RuntimeException $e) {
            $result->setException($e);
        }

        $this->log->debug('Communicator::callApiFunction() uri=%s; %s', $uri, $result->toLogString());
        return $result;
    }

    /**
     * Returns the common part of each API message.
     *
     * The common part consists of the following tags:
     * - contract
     * - format
     * - testmode
     * - connector
     *
     * @return array
     *   The common part of each API message.
     */
    protected function getCommonPart()
    {
        $environment = $this->config->getEnvironment();
        $pluginSettings = $this->config->getPluginSettings();

        // Complete message with values common to all API calls:
        // - contract part
        // - format part
        // - environment part
        $commonMessagePart = array(
            'contract' => $this->config->getCredentials(),
            'format' => $pluginSettings['outputFormat'],
            'testmode' => $pluginSettings['debug'] === PluginConfig::Send_TestMode ? Api::TestMode_Test : Api::TestMode_Normal,
            'connector' => array(
                'application' => "{$environment['shopName']} {$environment['shopVersion']}",
                'webkoppel' => "Acumulus {$environment['moduleVersion']}",
                'development' => 'SIEL - Buro RaDer',
                'remark' => "Library {$environment['libraryVersion']} - PHP {$environment['phpVersion']}",
                'sourceuri' => 'https://www.siel.nl/',
            ),
        );
        return $commonMessagePart;
    }

    /**
     * Sends a message to the Acumulus API and returns the answer.
     *
     * Any errors during:
     * - conversion of the message to xml,
     * - communication with the Acumulus web service
     * - converting the answer to an array
     * are returned as an Exception.
     *
     * Any errors (or warnings) returned in the response structure of the web
     * service are returned via the result value and should be handled at a
     * higher level.
     *
     * @param string $uri
     *   The URI of the Acumulus WebAPI call to send the message to.
     * @param array $message
     *   The message to send to the Acumulus WebAPI.
     * @param \Siel\Acumulus\Web\Result $result
     *   The result structure to add the results to.
     *
     * @return \Siel\Acumulus\Web\Result
     *   The result of the web service call.
     *
     * @see https://www.siel.nl/acumulus/API/Basic_Response/ For the
     *   structure of a response.
     */
    protected function sendApiMessage($uri, array $message, Result $result)
    {
        // Convert message to XML. XML requires 1 top level tag, so add one.
        // The tagname is ignored by the Acumulus WebAPI.
        $message = $this->convertArrayToXml(array('myxml' => $message));
        // Keep track of communication for debugging/logging at higher levels.
        $result->setRawRequest($message);

        $rawResponse = $this->sendHttpPost($uri, array('xmlstring' => $message), $result);
        $result->setRawResponse($rawResponse);

        if (empty($rawResponse)) {
            // CURL may get a time-out and return an empty response without
            // further error messages: Add an error to tell the user to check if
            // the invoice was sent or not.
            $result->addError(701, 'Empty response', '');
        } elseif ($this->isHtmlResponse($result->getRawResponse())) {
            // When the API is gone we might receive an html error message page.
            $this->raiseHtmlReceivedError($result->getRawResponse());
        } else {
            // Decode the response as either json or xml.
            $response = array();
            $pluginSettings = $this->config->getPluginSettings();

            if ($pluginSettings['outputFormat'] === 'json') {
                $response = json_decode($result->getRawResponse(), true);
            }
            // Even if we pass <format>json</format> we might receive an XML
            // response in case the XML was rejected before or during parsing.
            // So if the response is null we also try to decode the response as
            // XML.
            if ($pluginSettings['outputFormat'] === 'xml' || $response === null) {
                try {
                    $response = $this->convertXmlToArray($result->getRawResponse());
                } catch (RuntimeException $e) {
                    // Not an XML response. Treat it as an json error if we were
                    // expecting a json response.
                    if ($pluginSettings['outputFormat'] === 'json') {
                        $this->raiseJsonError();
                    }
                    // Otherwise treat it as the XML exception that was raised.
                    throw $e;
                }
            }
            $result->setResponse($response);
        }

        return $result;
    }

    /**
     * Sends the contents of $post to the given $uri.
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     * @param array|string $post
     *   An array of values to be placed in the POST body or an url-encoded
     *   string that contains all the POST values
     * @param \Siel\Acumulus\Web\Result $result
     *   The result structure to add the results to.
     *
     * @return string
     *  The response body from the HTTP response.
     *
     * @throws \RuntimeException
     */
    protected function sendHttpPost($uri, $post, Result $result)
    {
        // Open a curl connection.
        $ch = curl_init();
        if (!$ch) {
            $this->raiseCurlError($ch, 'curl_init()');
        }

        // Configure the curl connection.
        // Since 2017-09-19 the Acumulus web service only accepts TLS 1.2.
        // - Apparently, some curl libraries do support this version but do not
        //   use it by default, so we force it.
        // - Apparently, some up-to-date curl libraries do not define this
        //   constant,so we define it, if not defined.
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }
        $options = array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // @todo: why?
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
            //CURLOPT_PROXY => '127.0.0.1:8888', // Uncomment to debug with Fiddler.
            // @todo: CURLOPT_TIMEOUT?
        );
        if (!curl_setopt_array($ch, $options)) {
            $this->raiseCurlError($ch, 'curl_setopt_array()');
        }

        // Send and receive over the curl connection.

        $result->setIsSent(true);
        $response = curl_exec($ch);
        if ($response === false) {
            $this->raiseCurlError($ch, 'curl_exec()');
        }
        // Close the connection (this operation cannot fail).
        curl_close($ch);

        return $response;
    }

    /**
     * @param string $response
     *
     * @return bool
     *   True if the response is html, false otherwise.
     */
    protected function isHtmlResponse($response)
    {
        return strtolower(substr($response, 0, strlen('<!doctype html'))) === '<!doctype html'
            || strtolower(substr($response, 0, strlen('<html'))) === '<html'
            || strtolower(substr($response, 0, strlen('<body'))) === '<body';
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
    protected function convertXmlToArray($xml)
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
    protected function convertArrayToXml(array $values)
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
            $result = str_replace('\\', '&#92;', $result);
            return $result;
        } catch (\DOMException $e) {
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
     * Adds a curl error message to the result.
     *
     * @param resource|bool $ch
     * @param string $function
     */
    protected function raiseCurlError($ch, $function)
    {
        $env = $this->config->getEnvironment();
        $message = sprintf('%s (curl: %s): ', $function, $env['curlVersion']);
        if ($ch) {
            $code = curl_errno($ch);
            $message .= sprintf('%d - %s', $code, curl_error($ch));
        } else {
            $code = 703;
            $message .= 'no curl handle';
        }
        curl_close($ch);
        throw new RuntimeException($message, $code);
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
        $messages = array();
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
     *   String containing an html document.
     *
     * @trows \RuntimeException
     */
    protected function raiseHtmlReceivedError($response)
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
