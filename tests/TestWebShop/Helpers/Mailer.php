<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Helpers;

use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the TestWebShop mailer.
 */
class Mailer extends BaseMailer
{
    public function sendMail(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): bool
    {
        return true;
    }

    public function getFrom(): string
    {
        return 'unit.test@example.com';
    }

    public function getFromName(): string
    {
        return 'Unit Test | Buro RaDer';
    }
}
