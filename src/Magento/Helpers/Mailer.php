<?php

declare(strict_types=1);

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
            $email
                ->setFrom($from, $fromName)
                ->addTo($to)
                ->setSubject($subject)
                ->setBodyText($bodyText)
                ->setBodyHtml($bodyHtml)
                ->send();
            // @todo: error handling?
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

    public function getTo(): string
    {
        $return = parent::getTo();
        if (empty($return)) {
            // @todo: does this shop configure an administrator address?
            $return = $this->getFrom();
        }
        return $return;
    }
}
