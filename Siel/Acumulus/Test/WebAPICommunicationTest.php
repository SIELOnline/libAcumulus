<?php
/**
 * @file Definition of Siel\Acumulus\WebAPICommunicationTest.
 */

namespace Siel\Acumulus\Test;

use Siel\Acumulus\Common\WebAPICommunication;

/**
 * WebAPICommunicationTest is a class derived from WebAPICommunication that can
 * be used for testing purposes. It does not actually send the message to
 * Acumulus and fakes a response. It does log the message however, so it should
 * be used to check the correctness of the message contents.
 *
 * @package Siel\Acumulus
 */
class WebAPICommunicationTest extends WebAPICommunication {
  /**
   * @inheritdoc
   */
  protected function sendHttpPost($uri, $post) {
//    $received = '{"invoice":{"invoicenumber":"20130099","token":"local-token"},"errors":{"count_errors":"0"},"warnings":{"count_warnings":"0"},"status":"0"}';
//    $response = json_decode($received, true);
    $response = is_array($post) ? $post['xmlstring'] : substr($post, strlen('xmlstring='));
    return $response;
  }
}
