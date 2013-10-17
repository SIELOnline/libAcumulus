<?php
/**
 * @file Definition of Siel\Acumulus\WebAPICommunication.
 */

namespace Siel\Acumulus;

use DOMDocument;
use DOMElement;
use DOMException;
use LibXMLError;

/**
 * WebAPICommunication implements the conversion, communication and error
 * handling part of the Acumulus WebAPI class.
 *
 * @package Siel\Acumulus
 */
class WebAPICommunication {
  /** @var \Siel\Acumulus\ConfigInterface */
  protected $config;

  /** @var array */
  protected $warnings;

  /** @var array */
  protected $errors;

  public function __construct(ConfigInterface $config) {
    $this->config = $config;
  }

  /**
   * Sends a message to the given API call and returns the results as a simple array.
   *
   * @param string $apiCall
   *   The API call to invoke.
   * @param array $message
   *   The values to submit.
   *
   * @return array
   *   The results or an array of warning and/or error messages.
   */
  public function call($apiCall, array $message) {
    // TODO: wrap in try catch
    // Reset warnings and errors.
    $this->warnings = array();
    $this->errors = array();

    // Complete message with values common to all API calls:
    // - contract part
    // - format part
    // - environment part
    $environment = $this->config->getEnvironment();
    $message = array_merge(array(
      'contract' => $this->config->getCredentials(),
      'format' => $this->config->getOutputFormat(),
      'connector' => array(
        'application' => "{$environment['shopName']} {$environment['shopVersion']}",
        'webkoppel' => "Shop module: {$environment['moduleVersion']}; Library: {$environment['libraryVersion']}",
        'development' => 'Siel',
        'remark' => 'Stable',
        'sourceuri' => 'http://www.siel.nl/',
      ),
    ), $message);

    // Send message, receive response.
    $uri = $this->config->getBaseUri() . '/' . $this->config->getApiVersion() . '/' . $apiCall . '.php';
    $response = $this->send($uri, $message);

    // Process response.
    if (empty($response)) {
      // Internal error, create a response with these internal messages.
      $response['errors'] = $this->errors;
      $response['warnings'] = $this->warnings;
      $response['status'] = 1;
    }
    else {
      // No internal error, response is from remote.
      // Simplify errors and warnings parts: remove indirection and count.
      $response['errors'] = !empty($response['errors']['error']) ? $response['errors']['error'] : array();
      $response['warnings'] = !empty($response['warnings']['warning']) ? $response['warnings']['warning'] : array();
    }

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
  protected function send($uri, array $message) {
    // Convert message to XML. XML requires 1 top level tag, so add one.
    // The tagname is ignored by the Acumulus WebAPI.
    $message = array('myxml' => $message);
    try {
      $xmlSent = $this->convertToXml($message);
    }
    catch (DOMException $e) {
      $this->setDOMError($e);
      return array();
    }

    // Open a curl connection.
    if (!($ch = curl_init())) {
      $this->setCurlError($ch, 'curl_init()');
      return array();
    }

    // Configure the curl connection.
    $options = array(
      CURLOPT_URL => $uri,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => "xmlstring=$xmlSent",
      //*debug with Fiddler:*/ CURLOPT_PROXY => '127.0.0.1:8888',
    );
    if (!curl_setopt_array($ch, $options)) {
      $this->setCurlError($ch, 'curl_setopt_array()');
      return array();
    }

    // Send and receive over the curl connection.
    if (!($xmlReceived = curl_exec($ch))) {
      $this->setCurlError($ch, 'curl_exec()');
      return array();
    }

    // Convert the response to an array. 3-way conversion:
    // - create a simplexml object
    // - convert that to json
    // - convert json to array
    libxml_use_internal_errors(true);
    if (!($response = simplexml_load_string($xmlReceived, 'SimpleXMLElement', LIBXML_NOCDATA))) {
      $this->setLibxmlErrors(libxml_get_errors());
      return array();
    }

    if (!($response = json_encode($response))) {
      $this->setJsonError();
      return array();
    }
    if (($response = json_decode($response, true)) === null) {
      $this->setJsonError();
      return array();
    }

    // Close the connection (cannot fail).
    curl_close($ch);

    if ($this->config->getDebug()) {
      $this->config->log(date('c') . "\n" . "send($uri):\n" . "sent: $xmlSent\n" . "received: $xmlReceived\n\n");
    }

    return $response;
  }

  /**
   * Helper method to add a DOM error message to the result.
   *
   * @param DOMException $e
   */
  protected function setDOMError(DOMException $e) {
    $this->errors[] = array(
      'code' => $e->getCode(),
      'codetag' => "File: {$e->getFile()}, Line: {$e->getLine()}",
      'message' => $e->getMessage(),
    );
  }

  /**
   * Helper method to add a curl error message to the result.
   *
   * @param resource|bool $ch
   * @param string $function
   */
  protected function setCurlError($ch, $function) {
    $this->errors[] = array(
      'code' => $ch ? curl_errno($ch) : 0,
      'codetag' => $function,
      'message' => $ch ? curl_error($ch) : '',
    );
  }


  /**
   * Helper method to add libxml error messages to the result.
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
   * Helper method to add a json error message to the result.
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
    $this->errors[] = array(
      'code' => $code,
      'codetag' => '',
      'message' => $message,
    );
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
    $dom->formatOutput = false;

    $dom = $this->convertToDom($values, $dom);
    $dom->normalizeDocument ();
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
}
