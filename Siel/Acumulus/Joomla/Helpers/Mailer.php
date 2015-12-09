<?php
namespace Siel\Acumulus\Joomla\Helpers;

use JFactory;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the Joomla mail features.
 */
class Mailer extends BaseMailer {

  /**
   * {@inheritdoc}
   */
  public function sendInvoiceAddMailResult(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference) {
    $app = JFactory::getApplication();
    $mailer = JFactory::getMailer();
    $mailer->isHTML(TRUE);

    $mailer->setSender(array($app->get('mailfrom'), $this->getFromName()));
    $mailer->addRecipient($this->getToAddress());
    $mailer->setSubject(html_entity_decode($this->getSubject($result)));
    $body = $this->getBody($result, $messages, $invoiceSourceType, $invoiceSourceReference);
    $mailer->setBody($body['html']);

    return $mailer->Send();
  }

}
