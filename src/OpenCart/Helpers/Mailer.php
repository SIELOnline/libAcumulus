<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Exception;
use Mail;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the OpenCart mailer.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail(string $from, string $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        $result = true;
        try {
            $config = Registry::getInstance()->config;
            $mail = new Mail();
            /** @noinspection PhpUndefinedFieldInspection */
            $mail->protocol = $config->get('config_mail_protocol');
            $mail->parameter = $config->get('config_mail_parameter');
            /** @noinspection PhpUndefinedFieldInspection */
            $mail->smtp_hostname = $config->get('config_mail_smtp_hostname');
            /** @noinspection PhpUndefinedFieldInspection */
            $mail->smtp_username = $config->get('config_mail_smtp_username');
            /** @noinspection PhpUndefinedFieldInspection */
            $mail->smtp_password = html_entity_decode($config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
            /** @noinspection PhpUndefinedFieldInspection */
            $mail->smtp_port = $config->get('config_mail_smtp_port');
            /** @noinspection PhpUndefinedFieldInspection */
            $mail->smtp_timeout = $config->get('config_mail_smtp_timeout');

            $mail->setTo($to);
            $mail->setFrom($from);
            $mail->setSender($fromName);
            $mail->setSubject($subject);
            $mail->setText($bodyText);
            $mail->setHtml($bodyHtml);
            $mail->send();
        }
        catch (Exception $e) {
            $result = $e;
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFrom()
    {
        $config = Registry::getInstance()->config;
        return $config->get('config_email');
    }
}
