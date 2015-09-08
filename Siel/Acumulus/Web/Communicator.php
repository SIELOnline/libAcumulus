<?php
namespace Siel\Acumulus\Web;

use DOMDocument;
use DOMElement;
use Exception;
use LibXMLError;

/**
 * Communication implements the communication with the Acumulus WebAPI.
 *
 * It offers:
 * - Conversion between array and XML.
 * - Conversion from Json to array.
 * - (https) Communication with the Acumulus webservice using the curl library.
 * - Good error handling during communication.
 *
 * @package Siel\Acumulus
 */
class Communicator {

  /** @var \Siel\Acumulus\Web\ConfigInterface */
  protected $config;

  /** @var array */
  protected $warnings;

  /** @var array */
  protected $errors;

  public function __construct(ConfigInterface $config) {
    $this->config = $config;
  }

  /**
   * Checks and, if necessary, corrects the status.
   *
   * If local errors or warnings were added, the status may be incorrectly
   * indicating success. This method checks for this and corrects the status.
   *
   * @param array $response
   *   A response structure with, at least, fields 'errors', 'warnings' and
   *   'status'.
   */
  public function checkStatus(array &$response) {
    // - Check if status is consistent (local errors and warnings should alter
    //   the status as well.
    if (!empty($response['errors'])) {
      if ($response['status'] != ConfigInterface::Status_Exception) {
        $response['status'] = ConfigInterface::Status_Errors;
      }
    }
    else if (!empty($response['warnings'])) {
      if ($response['status'] != ConfigInterface::Status_Exception && $response['status'] != ConfigInterface::Status_Errors) {
        $response['status'] = ConfigInterface::Status_Warnings;
      }
    }
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
  public function callApiFunction($apiFunction, array $message) {
    // Reset warnings and errors.
    $this->warnings = array();
    $this->errors = array();

    try {
      // Compose URI.
      $uri = $this->config->getBaseUri() . '/' . $this->config->getApiVersion() . '/' . $apiFunction . '.php';

      // Complete message with values common to all API calls:
      // - contract part
      // - format part
      // - environment part
      $env = $this->config->getEnvironment();
      $message = array_merge(array(
        'contract' => $this->config->getCredentials(),
        'format' => $this->config->getOutputFormat(),
        'testmode' => $this->config->getDebug() == ConfigInterface::Debug_TestMode ? ConfigInterface::TestMode_Test : ConfigInterface::TestMode_Normal,
        'connector' => array(
          'application' => "{$env['shopName']} {$env['shopVersion']}",
          'webkoppel' => "Acumulus {$env['moduleVersion']}",
          'development' => 'SIEL - Buro RaDer',
          'remark' => "Library {$env['libraryVersion']} - PHP {$env['phpVersion']}",
          'sourceuri' => 'https://www.siel.nl/',
        ),
      ), $message);

      // Send message, receive response.
      $response = $this->sendApiMessage($uri, $message);
    } catch (Exception $e) {
      $this->errors[] = array(
        'code' => $e->getCode(),
        'codetag' => "File: {$e->getFile()}, Line: {$e->getLine()}",
        'message' => $e->getMessage(),
      );
      $response = array();
    }

    // Process response.
    // - Simplify errors and warnings parts: remove indirection and count.
    if (!empty($response['errors']['error'])) {
      $response['errors'] = $response['errors']['error'];
      // If there was exactly 1 error, it wasn't put in an array of errors.
      if (!is_array(reset($response['errors']))) {
        $response['errors'] = array($response['errors']);
      }
    }
    else if (!isset($response['errors'])) {
      $response['errors'] = array();
    }
    else {
      unset($response['errors']['count_errors']);
    }

    if (!empty($response['warnings']['warning'])) {
      $response['warnings'] = $response['warnings']['warning'];
      // If there was exactly 1 warning, it wasn't put in an array of warnings.
      if (!is_array(reset($response['warnings']))) {
        $response['warnings'] = array($response['warnings']);
      }
    }
    else if (!isset($response['warnings'])) {
      $response['warnings'] = array();
    }
    else {
      unset($response['warnings']['count_warnings']);
    }

    // - Add local errors and warnings.
    if (!empty($this->errors)) {
      // Internal error(s), return those as well.
      $response['errors'] = array_merge($this->errors, $response['errors']);
    }
    if (!empty($this->warnings)) {
      // Internal warning(s), return those as well.
      $response['warnings'] = array_merge($this->warnings, $response['warnings']);
    }

    // - Add status if not set. if no status is present the call failed, so we
    //   set the status to 1.
    if (!isset($response['status'])) {
      $response['status'] = ConfigInterface::Status_Errors;
    }

    $this->checkStatus($response);

    return $response;
  }

  /**
   * Sends a message to the Acumulus API and returns the answer.
   *
   * Any errors during:
   * - conversion of the message to xml,
   * - communication with the Acumulus WebAPI service
   * - converting the answer to an array
   * are returned as an 'error' in the 'errors' part of the return value.
   *
   *
   * @param string $uri
   *   The URI of the Acumulus WebAPI call to send the message to.
   * @param array $message
   *   The message to send to the Acumulus WebAPI.
   *
   * @return array
   *   The response as specified on
   *   https://apidoc.sielsystems.nl/content/warning-error-and-status-response-section-most-api-calls.
   */
  protected function sendApiMessage($uri, array $message) {
    $resultBase = array();

    // Convert message to XML. XML requires 1 top level tag, so add one.
    // The tagname is ignored by the Acumulus WebAPI.
    $message = $this->convertToXml(array('myxml' => $message));
    // Keep track of communication for debugging/logging at higher levels.
    $resultBase['trace']['request'] = preg_replace('|<password>.*</password>|', '<password>REMOVED FOR SECURITY</password>', $message);

    $response = $this->sendHttpPost($uri, array('xmlstring' => $message));

    if ($response) {
      $resultBase['trace']['response'] = $response;
      $this->config->getLog()->debug('sendApiMessage(uri="%s", message="%s"), response="%s"', $uri, $resultBase['trace']['request'], $resultBase['trace']['response']);

      $result = false;
      // When the API is gone we might receive an error message in an html page.
      if ($this->isHtmlResponse($response)) {
        $this->setHtmlReceivedError($response);
      }
      else {
        $alsoTryAsXml = false;
        if ($this->config->getOutputFormat() === 'json') {
          $result = json_decode($response, true);
          if ($result === null) {
            $this->setJsonError();
            // Even if we pass <format>json</format> we might receive an XML
            // response in case the XML was rejected before or during parsing.
            $alsoTryAsXml = true;
          }
        }
        if ($this->config->getOutputFormat() === 'xml' || $alsoTryAsXml) {
          $result = $this->convertToArray($response);
        }
      }

      if (is_array($result)) {
        $resultBase += $result;
      }
    }
    else {
      $this->config->getLog()->debug('sendApiMessage(uri="%s", message="%s"): failure', $uri, $resultBase['trace']['request']);
    }

    return $resultBase;
  }

  /**
   * @param string $uri
   *   The uri to send the HTTP request to.
   * @param array|string $post
   *   An array of values to be placed in the POST body or an url-encoded string
   *   that contains all the POST values
   *
   * @return string|false
   *  The response body from the HTTP response or false in case of errors.
   */
  protected function sendHttpPost($uri, $post) {
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
    }
    else {
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
  protected function isHtmlResponse($response) {
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
  protected function convertToArray($xml) {
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
   * Each key is converted to a tag, no attributes are used. Numeric sub-arrays
   * are repeated using the same key.
   *
   * @param array $values
   *   The array to convert to XML.
   *
   * @return string
   *   The XML string
   */
  protected function convertToXml(array $values) {
    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->xmlStandalone = true;
    $dom->formatOutput = true;

    $dom = $this->convertToDom($values, $dom);
    $dom->normalizeDocument();
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
  protected function convertToDom($values, $element) {
    /** @var DOMDocument $document */
    static $document = null;

    if ($element instanceof DOMDocument) {
      $document = $element;
    }
    if (is_array($values)) {
      foreach ($values as $key => $value) {
        if (is_int($key)) {
          if ($key === 0) {
            $node = $element;
          }
          else {
            $node = $document->createElement($element->tagName);
            $element->parentNode->appendChild($node);
          }
        }
        else {
          $node = $document->createElement($key);
          $element->appendChild($node);
        }
        $this->convertToDom($value, $node);
      }
    }
    else {
      $element->appendChild($document->createTextNode($values));
    }

    return $element;
  }

  /**
   * Adds a curl error message to the result.
   *
   * @param resource|bool $ch
   * @param string $function
   */
  protected function setCurlError($ch, $function) {
    $env = $this->config->getEnvironment();
    $this->errors[] = array(
      'code' => $ch ? curl_errno($ch) : 0,
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
  protected function setLibxmlErrors(array $errors) {
    foreach ($errors as $error) {
      $message = array(
        'code' => $error->code,
        'codetag' => "Line: {$error->line}, Column: {$error->column}",
        'message' => trim($error->message),
      );
      if ($error->level === LIBXML_ERR_WARNING) {
        $this->warnings[] = $message;
      }
      else {
        $this->errors[] = $message;
      }
    }
  }

  /**
   * Adds a json error message to the result.
   */
  protected function setJsonError() {
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
  protected function setHtmlReceivedError($response) {
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'utf-8');
    $doc->loadHTML($response);
    $body = $doc->getElementsByTagName('body');
    if ($body->length > 0) {
      $body = $body->item(0)->textContent;
    }
    else {
      $body = '';
    }
    $this->errors[] = array(
      'code' => 'HTML response received',
      'codetag' => '',
      'message' => $body,
    );
  }

}
