<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Helpers;

use Siel\Acumulus\Helpers\Mailer as BaseMailer;

/**
 * Extends the base mailer class to send a mail using the WP mail features.
 */
class Mailer extends BaseMailer
{
    public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    ): mixed {
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
