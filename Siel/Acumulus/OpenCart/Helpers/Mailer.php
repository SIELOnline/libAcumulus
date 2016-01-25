<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Mail;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the PrestaShop mailer.
 */
class Mailer extends BaseMailer {

  /** @var string */
  protected $templateDir;

  /** @var string */
  protected $templateName;

  /**
   * {@inheritdoc}
   */
  public function sendInvoiceAddMailResult(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference) {
    $config = Registry::getInstance()->config;
    $mail = new Mail();

    $mail->protocol = $config->get('config_mail_protocol') ? $config->get('config_mail_protocol') : 'mail';
    $mail->parameter = $config->get('config_mail_parameter');
    $mail->hostname = $config->get('config_smtp_host');
    $mail->username = $config->get('config_smtp_username');
    $mail->password = $config->get('config_smtp_password');
    $mail->port = $config->get('config_smtp_port');
    $mail->timeout = $config->get('config_smtp_timeout');
    $mail->setTo($this->getToAddress());
    $mail->setFrom($config->get('config_email'));
    $mail->setSender($this->getFromName());
    $mail->setSubject($this->getSubject($result));
    $content = $this->getBody($result, $messages, $invoiceSourceType, $invoiceSourceReference);
    $mail->setText($content['text']);
    $mail->setHtml($content['html']);

    $mail->send();
  }

}
