<?php
namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the WP mail features.
 */
class Mailer extends BaseMailer {

  /**
   * {@inheritdoc}
   */
  public function sendInvoiceAddMailResult(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference) {
    $to = $this->getToAddress();

    $subject = $this->getSubject($result);

    $fromEmail = get_bloginfo('admin_email');
    $fromName = get_bloginfo('name');
    $headers = array(
      "from: $fromName <$fromEmail>",
      'Content-Type: text/html; charset=UTF-8',
    );

    $body = $this->getBody($result, $messages, $invoiceSourceType, $invoiceSourceReference);
    $html = $body['html'];

    return wp_mail($to, $subject, $html, $headers);
  }

}
