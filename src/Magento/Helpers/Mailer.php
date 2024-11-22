<?php

declare(strict_types=1);

namespace Siel\Acumulus\Magento\Helpers;

use Laminas\Mail\Message as MailMessage;
use Laminas\Mail\Transport\Sendmail;
use Laminas\Mime\Message as MimeMessage;
use Laminas\Mime\Mime;
use Laminas\Mime\Part as MimePart;
use Magento\Backend\App\ConfigInterface as MagentoAppConfigInterface;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;
use Throwable;

/**
 * Extends the base mailer class to send mail using the Magento 2 mail features.
 */
class Mailer extends BaseMailer
{
    /**
     * Sends an email.
     *
     * @return mixed
     *   Success (true) or a {@see \Throwable}.
     */
    public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ): mixed {
        try {
            $text = new MimePart($bodyText);
            $text->type = Mime::TYPE_TEXT;
            $text->charset = 'utf-8';
            $text->encoding = Mime::ENCODING_QUOTEDPRINTABLE;

            $html = new MimePart($bodyHtml);
            $html->type = Mime::TYPE_HTML;
            $html->charset = 'utf-8';
            $html->encoding = Mime::ENCODING_QUOTEDPRINTABLE;

            $body = (new MimeMessage())->setParts([$text, $html]);

            $mail = (new MailMessage())
                ->setEncoding('UTF-8')
                ->setFrom($from, $fromName)
                ->addTo($to)
                ->setSubject($subject)
                ->setBody($body);

            (new Sendmail())->send($mail);
            return true;
        } catch (Throwable $e) {
            return $e;
        }
    }

    protected function getConfig(): MagentoAppConfigInterface
    {
        return Registry::getInstance()->get(MagentoAppConfigInterface::class);
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection  Default implementation not needed.
     */
    public function getFrom(): string
    {
        $result = $this->getConfig()->getValue('trans_email/ident_general/email');
        return !empty($result) && strpos($result, 'example.com') === false
            ? $result
            : parent::getTo();
    }

    public function getFromName(): string
    {
        $result = $this->getConfig()->getValue('general/store_information/name');
        return !empty($result)
            ? $result
            : parent::getFromName();
    }

    public function getTo(): string
    {
        $result = $this->getConfig()->getValue('trans_email/ident_general/email');
        return !empty($result) && strpos($result, 'example.com') === false
            ? $result
            : parent::getTo();
    }
}
