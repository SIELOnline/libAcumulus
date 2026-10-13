<?php

declare(strict_types=1);

namespace Siel\Acumulus\Whmcs\Mail;

use Siel\Acumulus\Mail\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send an email using the WHMCS email features.
 */
class Mailer extends BaseMailer
{
    /**
     * {@inheritdoc}
     *
     * WHMCS expects any message to be a smarty template. This leads to errors when mail
     * texts contains accolades (opening or closing). To prevent these errors, thxts that
     * containaccolades are placeed between the {literal} and {/literal} tags.
     */
    protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): mixed
    {
        foreach ([&$subject, &$bodyText, &$bodyHtml] as &$text) {
            if (str_contains($text, '{') || str_contains($text, '}')) {
                $text = "{literal}$text{/literal}";
            }
        }
        $command = 'SendAdminEmail';
        $postData = [
            'customsubject' => $subject,
            'custommessage' => $bodyHtml,
            'type' => 'system',
        ];
        $results = localAPI($command, $postData);
        return $results['result'] === 'success' ? true : $results['message'];
    }
}
