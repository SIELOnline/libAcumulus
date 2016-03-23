<?php
namespace Siel\Acumulus\Joomla\Helpers;

use JFactory;
use JText;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the Joomla mail features.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        $mailer = JFactory::getMailer();
        $mailer->isHtml(true);
        $mailer->setSender(array($from, $fromName));
        $mailer->addRecipient($to);
        $mailer->setSubject(html_entity_decode($subject));
        $mailer->setBody($bodyHtml);
        try {
            $result = $mailer->Send();
            if ($result === false) {
                $result = JText::_('JLIB_MAIL_FUNCTION_OFFLINE');
            }
        }
        catch (\RuntimeException $e){
            $result = $e->getMessage();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFrom()
    {
        $app = JFactory::getApplication();
        return $app->get('mailfrom');
    }
}
