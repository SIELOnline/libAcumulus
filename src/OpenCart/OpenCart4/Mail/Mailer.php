<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Mail;

use Opencart\System\Library\Mail;
use Siel\Acumulus\OpenCart\Helpers\Registry;
use Siel\Acumulus\OpenCart\Mail\Mailer as BaseMailer;

/**
 * OC4 specific Mail object creation.
 */
class Mailer extends BaseMailer
{
    /**
     * @throws \Exception
     */
    protected function getMail(): Mail
    {
        $config = Registry::getInstance()->config;
        $engine = $config->get('config_mail_engine');
        $options = [
            'protocol' => $config->get('config_mail_protocol'),
            'parameter' => $config->get('config_mail_parameter'),
            'smtp_hostname' => $config->get('config_mail_smtp_hostname'),
            'smtp_username' => $config->get('config_mail_smtp_username'),
            'smtp_password' => html_entity_decode($config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8'),
            'smtp_port' => $config->get('config_mail_smtp_port'),
            'smtp_timeout' => $config->get('config_mail_smtp_timeout'),
        ];
        return new Mail($engine, $options);
    }
}
