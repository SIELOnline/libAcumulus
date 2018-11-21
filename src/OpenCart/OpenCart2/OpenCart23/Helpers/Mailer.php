<?php
namespace Siel\Acumulus\OpenCart\OpenCart2\OpenCart23\Helpers;

use Mail;
use Siel\Acumulus\OpenCart\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the OpenCart mailer.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
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
            $mail->send();
        }
        catch (\Exception $e) {
            // Note: OC1 and OC2.0 use trigger_error() and thus in those
            // versions, errors will be logged in error.log. OC2.2+ throws
            // exceptions, so we will log it.
            $result = $e;
        }
        return $result;
    }
}
