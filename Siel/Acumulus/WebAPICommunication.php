<?php
/**
 * @file Definition of Siel\Acumulus\WebAPICommunication.
 */

namespace Siel\Acumulus;

use DOMDocument;
use DOMElement;
use Exception;
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
    // Reset warnings and errors.
    $this->warnings = array();
    $this->errors = array();

    try {
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
    }
    catch (Exception $e) {
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
      $response['errors'] += $this->errors;
    }
    if (!empty($this->warnings)) {
      // Internal warning(s), return those as well.
      $response['warnings'] += $this->warnings;
    }
    // - Add status if not set. if no status is present the call failed, so we
    //   set the status to 1.
    if (!isset($response['status'])) {
      $response['status'] = 1;
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
    $response = array();

    // Convert message to XML. XML requires 1 top level tag, so add one.
    // The tagname is ignored by the Acumulus WebAPI.
    $message = array('myxml' => $message);
    $sent = $this->convertToXml($message);

    // Open a curl connection.
    if (!($ch = curl_init())) {
      $this->setCurlError($ch, 'curl_init()');
      return $response;
    }

    // Configure the curl connection.
    $options = array(
      CURLOPT_URL => $uri,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => false,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => "xmlstring=$sent",
      //*debug with Fiddler:*/ CURLOPT_PROXY => '127.0.0.1:8888',
    );
    if (!curl_setopt_array($ch, $options)) {
      $this->setCurlError($ch, 'curl_setopt_array()');
      return $response;
    }

    // Send and receive over the curl connection.
    // Keep track of communication for debugging/logging at higher levels.
    $response['trace']['sent'] = preg_replace('|<password>.*</password>|', '<password>*****</password>', $sent);
    $received = curl_exec($ch);
    if (!$received) {
      $this->setCurlError($ch, 'curl_exec()');
      return $response;
    }
    // Close the connection (this operation cannot fail).
    curl_close($ch);
    $response['trace']['received'] = $received;

    // @todo: (re)move?
    if ($this->config->getDebug()) {
      $this->config->log(date('c') . "\n" . "send($uri):\n" . "sent: {$response['trace']['sent']}\n" . "received: {$response['trace']['received']}\n\n");
    }

    if ($this->config->getOutputFormat() === 'xml') {
      // Convert the response to an array. 3-way conversion:
      // - create a simplexml object
      // - convert that to json
      // - convert json to array
      libxml_use_internal_errors(true);
      if (!($received = simplexml_load_string($received, 'SimpleXMLElement', LIBXML_NOCDATA))) {
        $this->setLibxmlErrors(libxml_get_errors());
        return $response;
      }

      if (!($received = json_encode($received))) {
        $this->setJsonError();
        return $response;
      }
      if (($received = json_decode($received, true)) === null) {
        $this->setJsonError();
        return $response;
      }
    }
    else {
      if (($received = json_decode($received, true)) === null) {
        $this->setJsonError();
        return $response;
      }
    }

    $response = array_merge($response, $received);
    return $response;
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
    curl_close($ch);
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
    $dom->formatOutput = true;

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
