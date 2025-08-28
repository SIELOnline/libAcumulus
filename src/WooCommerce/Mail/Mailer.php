<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Mail;

use Siel\Acumulus\Mail\Mailer as BaseMailer;

use function get_bloginfo;
use function wp_mail;

/**
 * Extends the base mailer class to send an email using the WP email features.
 */
class Mailer extends BaseMailer
{
    protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): mixed
    {
        $headers = [
            "from: $fromName <$from>",
            'Content-Type: text/html; charset=UTF-8',
        ];
        return wp_mail($to, $subject, $bodyHtml, $headers);
    }

    public function getFrom(): string
    {
        return get_bloginfo('admin_email');
    }

    public function getFromName(): string
    {
        return get_bloginfo('name');
    }
}
