<?php
/**
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Mail;

use Exception;
use Siel\Acumulus\Mail\Mailer as BaseMailer;
use Siel\Acumulus\OpenCart\Helpers\Registry;

/**
 * Extends the base mailer class to send a mail using the OpenCart mailer.
 */
abstract class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     *
     * @noinspection PhpDynamicFieldDeclarationInspection All the properties for
     *   $mail are dynamic.
     */
    protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): mixed
    {
        try {
            $config = Registry::getInstance()->config;
            $mail = $this->getMail();
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
        } catch (Exception $e) {
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

    /**
     * @return \Opencart\System\Library\Mail|\Mail
     */
    abstract protected function getMail();
}
