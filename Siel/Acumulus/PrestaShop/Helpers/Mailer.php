<?php
namespace Siel\Acumulus\PrestaShop\Helpers;

use Configuration;
use Language;
use Mail;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the PrestaShop mailer.
 */
class Mailer extends BaseMailer
{
    /** @var string */
    protected $templateDir;

    /** @var string */
    protected $templateName;

    /**
     * {@inheritdoc}
     */
    public function sendInvoiceAddMailResult(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference)
    {
        $this->templateDir = dirname(__FILE__) . '/mails/';
        $this->templateName = 'message';
        $body = $this->getBody($result, $messages, $invoiceSourceType, $invoiceSourceReference);
        $this->writeTemplateFile($body);

        $languageId = Language::getIdByIso($this->translator->getLanguage());
        $title = $this->getSubject($result);
        $templateVars = array();
        $toEmail = $this->getToAddress();
        $toName = Configuration::get('PS_SHOP_NAME');
        $from = Configuration::get('PS_SHOP_EMAIL');
        $fromName = Configuration::get('PS_SHOP_NAME');

        $result = Mail::Send($languageId, $this->templateName, $title, $templateVars, $toEmail, $toName, $from, $fromName, null, null, $this->templateDir);

        // Clear the template files as they contain privacy sensitive data.
        $this->writeTemplateFiles(array('body' => '', 'text' => ''));

        return $result;
    }

    /**
     * Writes the mail bodies (html and text) to template files as used by the
     * PrestaShop mailer.
     *
     * @param array $body
     */
    protected function writeTemplateFile(array $body)
    {
        $languageIso = $this->translator->getLanguage();
        $templateBaseName = $this->templateDir . $languageIso . '/' . $this->templateName;
        file_put_contents($templateBaseName . '.html', !empty($body['html']) ? $body['html'] : '');
        file_put_contents($templateBaseName . '.txt', !empty($body['text']) ? $body['text'] : '');
    }

    /**
     * Writes the mail bodies (html and text) to template files as used by the
     * PrestaShop mailer.
     *
     * @param array $body
     */
    protected function writeTemplateFiles(array $body)
    {
        $languageIso = $this->translator->getLanguage();
        $templateBaseName = $this->templateDir . $languageIso . '/' . $this->templateName;
        file_put_contents($templateBaseName . '.html', !empty($body['html']) ? $body['html'] : '');
        file_put_contents($templateBaseName . '.txt', !empty($body['text']) ? $body['text'] : '');
    }
}
