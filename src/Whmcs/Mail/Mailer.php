<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Mail;

use Siel\Acumulus\Mail\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send an email using the WHMCS email features.
 */
class Mailer extends BaseMailer
{
    protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): mixed
    {
        $command = 'SendAdminEmail';
        $postData = [
            'customsubject' => $subject,
            'custommessage' => $bodyHtml,
            'type' => 'system',
        ];
        $adminUsername = 'ADMIN_USERNAME';
        $results = localAPI($command, $postData, $adminUsername);
        return $results['result'] === 'success' ? true : $results['message'];
    }
}
