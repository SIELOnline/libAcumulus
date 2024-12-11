<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart3\Mail;

use Mail;
use Siel\Acumulus\OpenCart\Mail\Mailer as BaseMailer;

/**
 * OC3 specific Mail object creation.
 */
class Mailer extends BaseMailer
{
    protected function getMail(): Mail
    {
        return new Mail();
    }
}
