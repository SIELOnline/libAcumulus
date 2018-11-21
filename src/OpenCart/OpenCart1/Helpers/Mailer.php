<?php
namespace Siel\Acumulus\OpenCart\OpenCart1\Helpers;

use Mail;
use Siel\Acumulus\OpenCart\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer lass to send a mail using the OpenCart mailer.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        $result = true;
        $config = Registry::getInstance()->config;
        $mail = new Mail();
        $mail->protocol = $config->get('config_mail_protocol');
        $mail->parameter = $config->get('config_mail_parameter');
        $mail->hostname = $config->get('config_mail_smtp_hostname');
        $mail->username = $config->get('config_mail_smtp_username');
        $mail->password = $config->get('config_mail_smtp_password');
        $mail->port = $config->get('config_mail_smtp_port');
        $mail->timeout = $config->get('config_mail_smtp_timeout');

        $mail->setTo($to);
        $mail->setFrom($from);
        $mail->setSender($fromName);
        $mail->setSubject($subject);
        $mail->setText($bodyText);
        $mail->setHtml($bodyHtml);
        $mail->send();
        return $result;
    }
}
