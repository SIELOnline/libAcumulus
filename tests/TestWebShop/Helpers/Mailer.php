<?php
namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the TestWebShop mailer.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     */
    public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFrom()
    {
        return 'unit.test@burorader.com';
    }

    /**
     * {@inheritdoc}
     */
    protected function getFromName()
    {
        return 'Unit Test | Buro RaDer';
    }
}
