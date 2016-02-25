<?php
namespace Siel\Acumulus\Magento\Helpers;

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
    public function sendInvoiceAddMailResult(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference)
    {
        /** @var Mage_Core_Model_Email_Template $emailTemplate */
        $emailTemplate = Mage::getModel('core/email_template');
        $emailTemplate->setSenderEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
        $emailTemplate->setSenderName($this->getFromName());
        $emailTemplate->setTemplateSubject($this->getSubject($result));
        $body = $this->getBody($result, $messages, $invoiceSourceType, $invoiceSourceReference);
        $emailTemplate->setTemplateText($body['html']);
        return $emailTemplate->send($this->getToAddress(), Mage::getStoreConfig('trans_email/ident_general/name'));
    }
}
