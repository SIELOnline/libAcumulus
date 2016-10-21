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
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        $this->templateDir = __DIR__ . '/mails/';
        $this->templateName = 'message';
        $this->writeTemplateFiles($bodyText, $bodyHtml);

        $languageId = Language::getIdByIso($this->translator->getLanguage());
        $templateVars = array();

        $result = Mail::Send($languageId, $this->templateName, $subject, $templateVars, $to, '', $from, $fromName, null, null, $this->templateDir);

        // Clear the template files as they contain privacy sensitive data.
        $this->writeTemplateFiles('', '');

        if ($result === true) {
            $result = 'Emails are deactivated: $configuration[\'PS_MAIL_METHOD\'] == 3';
        } elseif(is_int($result)) {
            // If PS returns an int, that indicates the number of successful recipients, see Swift::send().
            $result = true;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFrom()
    {
        return Configuration::get('PS_SHOP_EMAIL');
    }

    /**
     * {@inheritdoc}
     */
    protected function getFromName()
    {
        return Configuration::get('PS_SHOP_NAME');
    }

    /**
     * Writes the mail bodies (html and text) to template files as used by the
     * PrestaShop mailer.
     *
     * @param string $bodyText
     * @param string $bodyHtml
     */
    protected function writeTemplateFiles($bodyText, $bodyHtml)
    {
        $languageIso = $this->translator->getLanguage();
        $templateBaseName = $this->templateDir . $languageIso . '/' . $this->templateName;
        file_put_contents($templateBaseName . '.html', !empty($bodyHtml) ? $bodyHtml : '');
        file_put_contents($templateBaseName . '.txt', !empty($bodyText) ? $bodyText : '');
    }
}
