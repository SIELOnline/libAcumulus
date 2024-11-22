<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Helpers;

use Configuration;
use Language;
use Mail;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

use Throwable;

use function is_int;

/**
 * Extends the base mailer class to send a mail using the PrestaShop mailer.
 */
class Mailer extends BaseMailer
{
    protected string $templateDir;
    protected string $templateName;

    /**
     * Sends an email.
     *
     * @return mixed
     *   Success (true); error message, Throwable object or just false otherwise.
     *
     * @throws \PrestaShopException
     */
    public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ): mixed {
        $this->templateDir = _PS_ROOT_DIR_ . '/mails/';
        $this->templateName = 'acumulus-message';
        $this->writeTemplateFiles($bodyText, $bodyHtml);

        /** @noinspection PhpUnhandledExceptionInspection */
        $languageId = Language::getIdByIso($this->translator->getLanguage());
        $templateVars = [];

        $result = Mail::send($languageId, $this->templateName, $subject, $templateVars, $to, '', $from, $fromName, null, null, $this->templateDir);

        // Clear the template files as they contain privacy-sensitive data.
        $this->writeTemplateFiles('', '');

        if ($result === true) {
            $result = 'Emails are deactivated: $configuration[\'PS_MAIL_METHOD\'] == 3';
        } elseif(is_int($result)) {
            // If PS returns an int, that indicates the number of successful recipients, see Swift::send().
            $result = true;
        }
        return $result;
    }

    public function getFrom(): string
    {
        return Configuration::get('PS_SHOP_EMAIL');
    }

    public function getFromName(): string
    {
        return Configuration::get('PS_SHOP_NAME');
    }

    public function getTo(): string
    {
        $return = parent::getTo();
        if (empty($return)) {
            // @todo: does this shop configure an administrator address?
            $return = $this->getFrom();
        }
        return $return;
    }

    /**
     * Writes the mail bodies (html and text) to template files as used by the
     * PrestaShop mailer.
     */
    protected function writeTemplateFiles(string $bodyText, string $bodyHtml): void
    {
        $languageIso = $this->translator->getLanguage();
        $templateBaseName = $this->templateDir . $languageIso . '/' . $this->templateName;
        file_put_contents($templateBaseName . '.html', !empty($bodyHtml) ? $bodyHtml : '');
        file_put_contents($templateBaseName . '.txt', !empty($bodyText) ? $bodyText : '');
    }
}
