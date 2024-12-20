<?php

declare(strict_types=1);

namespace Siel\Acumulus\Joomla\Mail;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\MailerFactoryInterface;
use Joomla\CMS\Mail\MailerInterface;
use PHPMailer\PHPMailer\PHPMailer;
use Siel\Acumulus\Mail\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the Joomla mail features.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     *
     * @noinspection PhpMixedReturnTypeCanBeReducedInspection
     * *    @todo: Type can be narrowed to bool|int|string
     */
    protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): mixed
    {
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
        } catch (Exception $e) {
            $result = $e->getMessage();
        }
        return $result;
    }

    public function getFrom(): string
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        return Factory::getApplication()->get('mailfrom');
    }
}
