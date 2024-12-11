<?php

declare(strict_types=1);

namespace Siel\Acumulus\Mail;

/**
 * CrashReporter logs and mails a fatal crash.
 *
 * At the highest levels of code execution paths in this library, catch-all
 * exception handling has been placed. If a fatal error occurs which did not
 * get caught and handled on lower levels, this error will be logged and
 * mailed to the "admin" of this site (the 'emailonerror' setting).
 *
 * This catch-all exception handling has been introduced or the following
 * reasons:
 * - If our code fails, we allow the request to continue until its end. We think
 *   this is better than a WSOD, especially on the user-side.
 * - May webshops have suboptimal error handling. By doing this ourselves we
 *   ensure that errors in or code are actually loggend and reported (instead of
 *   ignored) and thus can be solved faster.
 */
class CrashMail extends Mail
{

    protected function getAboutLines(): array
    {
        return $this->getEnvironment()->getAsLines();
    }

    protected function doAddSupport(): bool
    {
        return true;
    }

    protected function getSupportMessageList(): array
    {
        $lines = (string) $this->args['exception'];
        return $this->toParagraph(explode("\n", $lines));
    }
}
