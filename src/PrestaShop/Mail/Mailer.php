<?php

declare(strict_types=1);

namespace Siel\Acumulus\PrestaShop\Mail;

use Configuration;
use Language;
use Mail;
use Siel\Acumulus\Mail\Mailer as BaseMailer;

use Throwable;

use function is_int;

/**
 * Extends the base mailer class to send an e-mail using the PrestaShop mailer.
 */
class Mailer extends BaseMailer
{
    protected string $templateDir;
    protected string $templateName;

    /**
     * @throws \PrestaShopException
     */
    protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): bool|Throwable
    {
        $this->templateDir = _PS_ROOT_DIR_ . '/mails/';
        $this->templateName = 'acumulus-message';
        $this->writeTemplateFiles($bodyText, $bodyHtml);

        /** @noinspection PhpUnhandledExceptionInspection */
        $languageId = Language::getIdByIso($this->translator->getLanguage());
        $templateVars = [];

        try {
            // Note: result will also be true if sending e-mail is prevented by hook or
            // config ($configuration['PS_MAIL_METHOD'] === 3). We cannot know this
            // without a bit of hacking, but we should see this as success anyway.
            $result = Mail::send(
                $languageId,
                $this->templateName,
                $subject,
                $templateVars,
                $to,
                '',
                $from,
                $fromName,
                null,
                null,
                $this->templateDir
            );
            if (is_int($result)) {
                // If PS returns an int, that indicates the number of successful recipients, see Swift::send().
                $result = true;
            }
        } catch (Throwable $e) {
            $result = $e;
        }

        // Clear the template files as they contain privacy-sensitive data.
        $this->writeTemplateFiles('', '');

        return $result;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function getFrom(): string
    {
        return Configuration::get('PS_SHOP_EMAIL');
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function getFromName(): string
    {
        return Configuration::get('PS_SHOP_NAME');
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
