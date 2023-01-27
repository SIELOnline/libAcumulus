<?php

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\OpenCart4\Helpers;

use Opencart\System\Library\Mail;
use Siel\Acumulus\OpenCart\Helpers\Mailer as BaseMailer;

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
