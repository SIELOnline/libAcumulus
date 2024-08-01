<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Helpers;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailerInterface;
use PHPMailer\PHPMailer\PHPMailer;
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
    ) {
        /** @var MailerInterface $mailer */
        $mailer = Factory::getContainer()->get(MailerFactoryInterface::class)->createMailer();
        if ($mailer instanceof PHPMailer) {
            $mailer->isHTML(true);
        }
        $mailer->setSender([$from, $fromName]);
        $mailer->addRecipient($to);
        $mailer->setSubject(html_entity_decode($subject));
        $mailer->setBody($bodyHtml);
        try {
            $result = $mailer->send();
            if ($result === false) {
                $result = Text::_('JLIB_MAIL_FUNCTION_OFFLINE');
            }
        } catch (RuntimeException $e) {
            $result = $e->getMessage();
        }
        return $result;
    }

    public function getFrom(): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return Factory::getApplication()->get('mailfrom');
    }

    public function getTo(): string
    {
        $return = parent::getTo();
        if (empty($return)) {
            $return = $this->getFrom();
        }
        return $return;
    }
}
