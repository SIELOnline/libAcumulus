<?php
namespace Siel\Acumulus\Joomla\Helpers;

use JFactory;
use JText;
use RuntimeException;
use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the Joomla mail features.
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
    ): bool {
        $mailer = JFactory::getMailer();
        /** @noinspection PhpRedundantOptionalArgumentInspection */
        $mailer->isHtml(true);
        $mailer->setSender([$from, $fromName]);
        $mailer->addRecipient($to);
        $mailer->setSubject(html_entity_decode($subject));
        $mailer->setBody($bodyHtml);
        try {
            $result = $mailer->Send();
            if ($result === false) {
                $result = JText::_('JLIB_MAIL_FUNCTION_OFFLINE');
            }
        }
        catch (RuntimeException $e){
            $result = $e->getMessage();
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getFrom(): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $app = JFactory::getApplication();
        return $app->get('mailfrom');
    }

    /**
     * {@inheritdoc}
     */
    public function getTo(): string
    {
        $return = parent::getTo();
        if (empty($return)) {
            $return = $this->getFrom();
        }
        return $return;
    }
}
