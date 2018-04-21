<?php
namespace Siel\Acumulus\MyWebShop\Helpers;

use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the MyWebShop mailer.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        // @todo: adapt to MyWebshop's way of creating a mailer, a "mail object", and having the "mail object" sent by the mailer.
        $result = Mail::Send($this->translator->getLanguage(), $from, $fromName, $subject, $to, $bodyHtml);
        // @todo: if necessary, cast the result to a bool indicating success.
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFrom()
    {
        // @todo: adapt to MyWebshop's way of getting the from email address to use.
        return Configuration::get('SHOP_EMAIL');
    }

    /**
     * {@inheritdoc}
     */
    protected function getFromName()
    {
        // @todo: adapt to MyWebshop's way of getting the webshop name.
        return Configuration::get('SHOP_NAME');
    }
}
