<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Mail;

use Opencart\System\Library\Mail;
use Siel\Acumulus\OpenCart\Mail\Mailer as BaseMailer;

/**
 * OC4 specific Mail object creation.
 */
class Mailer extends BaseMailer
{
    protected function getMail(): Mail
    {
        return new Mail();
    }
}
