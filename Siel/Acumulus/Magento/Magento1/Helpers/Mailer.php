<?php
namespace Siel\Acumulus\Magento\Magento1\Helpers;

use Mage;
use Mage_Core_Model_Email_Template;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the Magento mail features.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        /** @var Mage_Core_Model_Email_Template $emailTemplate */
        $emailTemplate = Mage::getModel('core/email_template');
        $emailTemplate->setSenderEmail($from);
        $emailTemplate->setSenderName($fromName);
        $emailTemplate->setTemplateSubject($subject);
        $emailTemplate->setTemplateText($bodyHtml);
        return $emailTemplate->send($to);
    }

    /**
     * {@inheritdoc}
     */
    protected function getFrom()
    {
        return Mage::getStoreConfig('trans_email/ident_general/email');
    }

    /**
     * {@inheritdoc}
     */
    protected function getFromName()
    {
        $result = Mage::getStoreConfig('general/store_information/name');
        return $result ? $result : parent::getFromName();
    }
}
