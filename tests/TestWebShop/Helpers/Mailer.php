<?php
namespace Siel\Acumulus\Tests\TestWebShop\Helpers;

use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the TestWebShop mailer.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail(string $from, string $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getFrom(): string
    {
        return 'unit.test@burorader.com';
    }

    /**
     * {@inheritdoc}
     */
    public function getFromName(): string
    {
        return 'Unit Test | Buro RaDer';
    }
}
