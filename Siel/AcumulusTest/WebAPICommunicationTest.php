<?php
/**
 * @file Definition of Siel\Acumulus\WebAPICommunicationTest.
 */

namespace Siel\AcumulusTest;

use Siel\Acumulus\WebAPICommunication;

/**
 * WebAPICommunicationTest is a class derived from WebAPICommunication that can
 * be used for testing purposes. It does not actually send the message to
 * Acumulus and fakes a response. It does log the message however, so it should
 * be used to check the correctness of the message contents.
 *
 * @package Siel\Acumulus
 */
class WebAPICommunicationTest extends WebAPICommunication {
  protected function send($uri, array $message) {
    // Convert message to XML. XML requires 1 top level tag, so add one.
    // The tagname is ignored by the Acumulus WebAPI.
    $message = array('myxml' => $message);
    $sent = $this->convertToXml($message);

    $received = '{"invoice":{"invoicenumber":"20130099","token":"local-token"},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}';
    $response = json_decode($received, true);

    $this->config->log(date('c') . "\n" . "send($uri):\n" . "sent: $sent\n" . "received: $received\n\n");

    return $response;
  }
}
