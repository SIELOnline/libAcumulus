<?php

declare(strict_types=1);

namespace Siel\Acumulus\TestWebShop\Mail;

use Siel\Acumulus\Mail\Mailer as BaseMailer;

use function count;

/**
 * Extends the base mailer class to send a mail using the TestWebShop mailer.
 */
class Mailer extends BaseMailer
{
    private array $mailsSent = [];

    public function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): bool
    {
        $this->mailsSent[] = [
            'from' => $from,
            'fromName' => $fromName,
            'to' => $to,
            'subject' => $subject,
            'bodyText' => $bodyText,
            'bodyHtml' => $bodyHtml,
        ];
        return true;
    }

    public function getFrom(): string
    {
        return 'unit.test@example.com';
    }

    public function getFromName(): string
    {
        return 'Unit Test | Example';
    }

    public function getMailCount(): int
    {
        return count($this->mailsSent);
    }

    public function getMailSent(int $index): ?array
    {
        return $this->mailsSent[$index] ?? null;
    }
}
