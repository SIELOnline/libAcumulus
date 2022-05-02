<?php
namespace Siel\Acumulus\Magento\Helpers;

use Magento\Backend\App\ConfigInterface as MagentoAppConfigInterface;
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
    public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ) {
        try {
            $email = new Zend_Mail('utf-8');
            /** @noinspection PhpUnhandledExceptionInspection */
            $email->setFrom($from, $fromName);
            $email->addTo($to);
            /** @noinspection PhpUnhandledExceptionInspection */
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

    protected function getConfig(): MagentoAppConfigInterface
    {
        return Registry::getInstance()->get(MagentoAppConfigInterface::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getFrom(): string
    {
        return $this->getConfig()->getValue('trans_email/ident_general/email');
    }

    /**
     * {@inheritdoc}
     */
    public function getFromName(): string
    {
        $result = $this->getConfig()->getValue('general/store_information/name');
        return $result ?: parent::getFromName();
    }
}
