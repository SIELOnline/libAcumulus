<?php
namespace Siel\Acumulus\Web;

use DOMDocument;
use DOMElement;
use Exception;
use LibXMLError;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\PluginConfig;

/**
 * Communication implements the communication with the Acumulus WebAPI.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - (https) Communication with the Acumulus webservice using the curl library.
 * - Good error handling during communication.
 */
class Communicator implements CommunicatorInterface
{
    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var array */
    protected $warnings;

    /** @var array */
    protected $errors;

    /**
     * Communicator constructor.
     *
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(ConfigInterface $config, Log $log) {
        $this->config = $config;
        $this->log = $log;
    }

    /**
     * Sends a message to the given API function and returns the results.
     *
     * For debugging purposes the return array also includes a key 'trace',
     * containing an array with 2 keys, request and response, with the actual
     * strings as were sent.
     *
     * @param string $apiFunction
     *   The API function to invoke.
     * @param array $message
     *   The values to submit.
     *
     * @return array
     *   An array with the results including any warning and/or error messages.
     */
    public function callApiFunction($apiFunction, array $message)
    {
        // Reset warnings and errors.
        $this->warnings = array();
        $this->errors = array();

        try {
            $commonMessagePart = $this->getCommonPart();
            $message = array_merge($commonMessagePart, $message);

            // Send message, receive response.
            $environment = $this->config->getEnvironment();
            $uri = $environment['baseUri'] . '/' . $environment['apiVersion'] . '/' . $apiFunction . '.php';
            $response = $this->sendApiMessage($uri, $message);
        } catch (Exception $e) {
            $this->errors[] = array(
                'code' => $e->getCode(),
                'codetag' => "File: {$e->getFile()}, Line: {$e->getLine()}",
                'message' => $e->getMessage(),
            );
            $response = array();
            $response['status'] = Api::Exception;
        }

        // Process response:
        // If no status is present the call failed: set the status to Error.
        if (!isset($response['status'])) {
            $response['status'] = Api::Errors;
        }

        // Change status to internal status (bits and increasing severity).
        $response['status'] = $this->ApiStatus2InternalStatus($response['status']);

        // Simplify errors and warnings parts: remove indirection and count.
        if (!empty($response['errors']['error'])) {
            $response['errors'] = $response['errors']['error'];
            // If there was exactly 1 error, it wasn't put in an array of
            // errors.
            if (!is_array(reset($response['errors']))) {
                $response['errors'] = array($response['errors']);
            }
        } elseif (!isset($response['errors'])) {
            $response['errors'] = array();
        } else {
            unset($response['errors']['count_errors']);
        }

        if (!empty($response['warnings']['warning'])) {
            $response['warnings'] = $response['warnings']['warning'];
            // If there was exactly 1 warning, it wasn't put in an array of
            // warnings.
            if (!is_array(reset($response['warnings']))) {
                $response['warnings'] = array($response['warnings']);
            }
        } elseif (!isset($response['warnings'])) {
            $response['warnings'] = array();
        } else {
            unset($response['warnings']['count_warnings']);
        }

        // Add local communication level errors and warnings and change the
        // status if necessary.
        // @todo: extract this into a messages/result class.
        if (!empty($this->errors)) {
            // Internal error(s), return those as well.
            $response['errors'] = array_merge($this->errors, $response['errors']);
            if ($response['status'] < PluginConfig::Status_Errors) {
                $response['status'] = PluginConfig::Status_Errors;
            }
        }
        if (!empty($this->warnings)) {
            // Internal warning(s), return those as well.
            $response['warnings'] = array_merge($this->warnings, $response['warnings']);
            if ($response['status'] < PluginConfig::Status_Warnings) {
                $response['status'] = PluginConfig::Status_Warnings;
            }
        }

        return $response;
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
    protected function getCommonPart() {
        $environment = $this->config->getEnvironment();
        $pluginSettings = $this->config->getPluginSettings();

        // Complete message with values common to all API calls:
        // - contract part
        // - format part
        // - environment part
        $commonMessagePart = array(
            'contract' => $this->config->getCredentials(),
            'format' => $pluginSettings['outputFormat'],
            'testmode' => $pluginSettings['debug'] === PluginConfig::Debug_TestMode ? Api::TestMode_Test : Api::TestMode_Normal,
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
     * - communication with the Acumulus WebAPI service
     * - converting the answer to an array
     * are returned as an 'error' in the 'errors' part of the return value.
     * @todo: throw an exception instead of setting an error.
     *
     * @param string $uri
     *   The URI of the Acumulus WebAPI call to send the message to.
     * @param array $message
     *   The message to send to the Acumulus WebAPI.
     *
     * @return array
     *   The response as specified on
     *   https://apidoc.sielsystems.nl/content/global-legend.
     */
    protected function sendApiMessage($uri, array $message)
    {
        $resultBase = array();

        // Convert message to XML. XML requires 1 top level tag, so add one.
        // The tagname is ignored by the Acumulus WebAPI.
        $message = $this->convertToXml(array('myxml' => $message));
        // Keep track of communication for debugging/logging at higher levels.
        $resultBase['trace']['request'] = preg_replace('|<password>.*</password>|', '<password>REMOVED FOR SECURITY</password>', $message);

        $response = $this->sendHttpPost($uri, array('xmlstring' => $message));

        if ($response) {
            $resultBase['trace']['response'] = $response;
            $this->log->debug('sendApiMessage(uri="%s", message="%s"), response="%s"',
                $uri, $resultBase['trace']['request'], $resultBase['trace']['response']);

            $result = false;
            // When the API is gone we might receive an html error message page.
            if ($this->isHtmlResponse($response)) {
                $this->setHtmlReceivedError($response);
            } else {
                $pluginSettings = $this->config->getPluginSettings();
                $alsoTryAsXml = false;
                $setJsonError = false;
                if ($pluginSettings['outputFormat'] === 'json') {
                    $result = json_decode($response, true);
                    if ($result === null) {
                        // Even if we pass <format>json</format> we might
                        // receive an XML response in case the XML was rejected
                        // before or during parsing. So we do not set a json
                        // error now, but try to decode the response as XML
                        // first.
                        $alsoTryAsXml = true;
                        $setJsonError = true;
                    }
                }
                if ($pluginSettings['outputFormat'] === 'xml' || $alsoTryAsXml) {
                    $result = $this->convertToArray($response);
                    if (!$result && $setJsonError) {
                        $this->setJsonError();
                    }
                }
            }

            if (is_array($result)) {
                $resultBase += $result;
            }
        } else {
            $this->log->debug('sendApiMessage(uri="%s", message="%s"): failure',
                $uri, $resultBase['trace']['request']);
        }

        return $resultBase;
    }

    /**
     * Sends the contents of $post to the given $uri.
     *
     * @todo: throw an exception on communication errors to better distinguish
     *    communication errors and API errors (invoice could not be saved).
     *
     * @param string $uri
     *   The uri to send the HTTP request to.
     * @param array|string $post
     *   An array of values to be placed in the POST body or an url-encoded
     *   string that contains all the POST values
     *
     * @return string|false
     *  The response body from the HTTP response or false in case of errors.
     */
    protected function sendHttpPost($uri, $post)
    {
        $response = false;

        // Open a curl connection.
        $ch = curl_init();
        if (!$ch) {
            $this->setCurlError($ch, 'curl_init()');
            return $response;
        }

        // Configure the curl connection.
        $options = array(
            CURLOPT_URL => $uri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            //CURLOPT_PROXY => '127.0.0.1:8888', // Uncomment to debug with Fiddler.
        );
        if (!curl_setopt_array($ch, $options)) {
            $this->setCurlError($ch, 'curl_setopt_array()');
            return $response;
        }

        // Send and receive over the curl connection.
        $response = curl_exec($ch);
        if (!$response) {
            $this->setCurlError($ch, 'curl_exec()');
        } else {
            // Close the connection (this operation cannot fail).
            curl_close($ch);
        }

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
     * @return array|false
     *  An array representation of the XML string or false on errors.
     */
    protected function convertToArray($xml)
    {
        // Convert the response to an array via a 3-way conversion:
        // - create a simplexml object
        // - convert that to json
        // - convert json to array
        libxml_use_internal_errors(true);
        if (!($result = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA))) {
            $this->setLibxmlErrors(libxml_get_errors());
            return false;
        }

        if (!($result = json_encode($result))) {
            $this->setJsonError();
            return false;
        }
        if (($result = json_decode($result, true)) === null) {
            $this->setJsonError();
            return false;
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
     */
    protected function convertToXml(array $values)
    {
        $dom = new DOMDocument('1.0', 'utf-8');
        $dom->xmlStandalone = true;
        $dom->formatOutput = true;

        $dom = $this->convertToDom($values, $dom);
        $result = $dom->saveXML();
        return $result;
    }

    /**
     * Recursively converts a value to a DOMDocument|DOMElement.
     *
     * @param mixed $values
     *   A keyed array, an numerically indexed array, or a scalar type.
     * @param DOMDocument|DOMElement $element
     *   The element to append the values to.
     *
     * @return DOMDocument|DOMElement
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
     * Returns the corresponding internal status.
     *
     * @param $status
     *   The status as returned by the API.
     *
     * @return int
     *   The corresponding internal status.
     */
    protected function ApiStatus2InternalStatus($status) {
        switch ($status) {
            case Api::Success:
                return PluginConfig::Status_Success;
            case Api::Errors:
                return PluginConfig::Status_Errors;
            case Api::Warnings:
                return PluginConfig::Status_Warnings;
            case Api::Exception:
            default:
                return PluginConfig::Status_Exception;
        }
    }

    /**
     * Adds a curl error message to the result.
     *
     * @param resource|bool $ch
     * @param string $function
     */
    protected function setCurlError($ch, $function)
    {
        $env = $this->config->getEnvironment();
        $this->errors[] = array(
            'code' => $ch ? curl_errno($ch) : 'no-ch',
            'codetag' => "$function (Curl: {$env['curlVersion']})",
            'message' => $ch ? curl_error($ch) : '',
        );
        curl_close($ch);
    }

    /**
     * Adds a libxml error messages to the result.
     *
     * @param LibXMLError[] $errors
     */
    protected function setLibxmlErrors(array $errors)
    {
        foreach ($errors as $error) {
            $message = array(
                'code' => $error->code,
                'codetag' => "Line: {$error->line}, Column: {$error->column}",
                'message' => trim($error->message),
            );
            if ($error->level === LIBXML_ERR_WARNING) {
                $this->warnings[] = $message;
            } else {
                $this->errors[] = $message;
            }
        }
    }

    /**
     * Adds a json error message to the result.
     */
    protected function setJsonError()
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
                $message = 'Unknown error';
                break;
        }
        $env = $this->config->getEnvironment();
        $this->errors[] = array(
            'code' => $code,
            'codetag' => "(json: {$env['jsonVersion']})",
            'message' => $message,
        );
    }

    /**
     * Adds an error message to the result.
     *
     * @param string $response
     *    String containing an html document.
     */
    protected function setHtmlReceivedError($response)
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
        $this->errors[] = array(
            'code' => 'HTML response received',
            'codetag' => '',
            'message' => $body,
        );
    }
}
