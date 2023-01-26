<?php
/**
 * @noinspection DuplicatedCode  Remove when extracting code common for OC3 and OC4
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Exception;
use Opencart\System\Library\Mail;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the OpenCart mailer.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     *
     * @noinspection PhpDynamicFieldDeclarationInspection
     */
    public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ) {
        $result = true;
        try {
            $config = Registry::getInstance()->config;
            $mail = new Mail();
            $mail->protocol = $config->get('config_mail_protocol');
            $mail->parameter = $config->get('config_mail_parameter');
            $mail->smtp_hostname = $config->get('config_mail_smtp_hostname');
            $mail->smtp_username = $config->get('config_mail_smtp_username');
            $mail->smtp_password = html_entity_decode($config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            $mail->smtp_port = $config->get('config_mail_smtp_port');
            $mail->smtp_timeout = $config->get('config_mail_smtp_timeout');

            $mail->setTo($to);
            $mail->setFrom($from);
            $mail->setSender($fromName);
            $mail->setSubject($subject);
            $mail->setText($bodyText);
            $mail->setHtml($bodyHtml);
            $result = $mail->send();
        }
        catch (Exception $e) {
            $result = $e;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @noinspection PhpMissingParentCallCommonInspection parent is default
     *   fall back.
     */
    public function getFrom(): string
    {
        return Registry::getInstance()->config->get('config_email');
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
}
