<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Mail;

use Mail;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Mail\Mailer as BaseMailer;

/**
 * OC3 specific Mail object creation.
 */
class Mailer extends BaseMailer
{
    protected function getMail(): Mail
    {
        $config = Registry::getInstance()->config;
        $mail = new Mail();
        $mail->protocol = $config->get('config_mail_protocol');
        $mail->parameter = $config->get('config_mail_parameter');
        $mail->smtp_hostname = $config->get('config_mail_smtp_hostname');
        $mail->smtp_username = $config->get('config_mail_smtp_username');
        $mail->smtp_password = html_entity_decode($config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
        $mail->smtp_port = $config->get('config_mail_smtp_port');
        $mail->smtp_timeout = $config->get('config_mail_smtp_timeout');
        return $mail;
    }
}
