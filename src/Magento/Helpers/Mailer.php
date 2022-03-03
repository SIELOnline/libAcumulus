<?php
namespace Siel\Acumulus\Magento\Helpers;

use Siel\Acumulus\Helpers\Mailer as BaseMailer;
use Zend_Mail;
use Zend_Mail_Transport_Exception;

/**
 * Extends the base mailer class to send mail using the Magento 2 mail features.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        try {
            $email = new Zend_Mail('utf-8');
            $email->setFrom($from, $fromName);
            $email->addTo($to);
            $email->setSubject($subject);
            $email->setBodyText($bodyText);
            $email->setBodyHtml($bodyHtml);
            $email->send();
            return true;
        }
        catch (Zend_Mail_Transport_Exception $e) {
            return $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getFrom()
    {
        return Registry::getInstance()->getConfigInterface()->getValue('trans_email/ident_general/email');
    }

    /**
     * {@inheritdoc}
     */
    protected function getFromName()
    {
        $result = Registry::getInstance()->getConfigInterface()->getValue('general/store_information/name');
        return $result ? $result : parent::getFromName();
    }
}
