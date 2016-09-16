<?php
namespace Siel\Acumulus\OpenCart\Helpers;

use Mail;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the PrestaShop mailer.
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
            $mail->protocol = $config->get('config_mail_protocol') ? $config->get('config_mail_protocol') : 'mail';
            $mail->parameter = $config->get('config_mail_parameter');
            $mail->hostname = $config->get('config_smtp_host');
            $mail->username = $config->get('config_smtp_username');
            $mail->password = $config->get('config_smtp_password');
            $mail->port = $config->get('config_smtp_port');
            $mail->timeout = $config->get('config_smtp_timeout');
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
            Log::getInstance()->error('%s: %s', __METHOD__, $e->getMessage());
            $result = false;
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
