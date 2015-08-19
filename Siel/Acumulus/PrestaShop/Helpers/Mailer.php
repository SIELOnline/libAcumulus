<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Configuration;
use Language;
use Mail;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the PrestaShop mailer.
 */
class Mailer extends BaseMailer {

  /**
   * {@inheritdoc}
   */
  public function sendInvoiceAddMailResult(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference) {
    $mailDir = dirname(__FILE__) . '/mails/';
    $templateName = 'message';
    $body = $this->getBody($result, $messages, $invoiceSourceType, $invoiceSourceReference);
    $this->writeTemplateFile($mailDir, $templateName, $body);

    $languageId = Language::getIdByIso($this->translator->getLanguage());
    $title = $this->getSubject($result);
    $templateVars = array();
    $toEmail = $this->getToAddress();
    $toName = Configuration::get('PS_SHOP_NAME');
    $from = Configuration::get('PS_SHOP_EMAIL');
    $fromName = Configuration::get('PS_SHOP_NAME');

    return Mail::Send($languageId, $templateName, $title, $templateVars, $toEmail, $toName, $from, $fromName, NULL, NULL, $mailDir);
  }

  /**
   * Writes the mail bodies (html and text) to template files as used by the
   * PrestaShop mailer.
   *
   * @param string $mailDir
   * @param string $templateName
   * @param array $body
   */
  protected function writeTemplateFile($mailDir, $templateName, array $body) {
    $languageIso = $this->translator->getLanguage();
    $templateBaseName = $mailDir . $languageIso . '/'. $templateName;
    if (!empty($body['html'])) {
      file_put_contents($templateBaseName . '.html', $body['html']);
    }
    else {
      // Prevent an old html template from being sent again.
      unlink($templateBaseName . '.html');
    }
    if (!empty($body['text'])) {
      file_put_contents($templateBaseName . '.txt', $body['text']);
    }
    else {
      // Prevent an old text template from being sent again.
      unlink($templateBaseName . '.txt');
    }
  }
}
