<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Mail\CrashMail;
use Throwable;

use function function_exists;
use function sprintf;
use function strlen;

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
 *   ensure that errors in or code are actually logged and reported (instead of
 *   ignored) and thus can be solved faster.
 */
class CrashReporter
{
    private Translator $translator;
    protected Util $util;
    protected Log $log;
    protected Environment $environment;
    private CrashMail $mail;

    public function __construct(CrashMail $mail, Environment $environment, Util $util, Translator $translator, Log $log)
    {
        $this->translator = $translator;
        $this->util = $util;
        $this->log = $log;
        $this->environment = $environment;
        $this->mail = $mail;
    }

    /**
     * Helper method to translate strings.
     *
     * @param string $key
     *  The key to get a translation for.
     *
     * @return string
     *   The translation for the given key or the key itself if no translation
     *   could be found.
     */
    protected function t(string $key): string
    {
        return $this->translator->get($key);
    }

    protected function getMail(): CrashMail
    {
        return $this->mail;
    }

    /**
     * Logs the exception and mails a message to the user.
     *
     * @param \Throwable $e
     *   The error that was thrown.
     *
     * @return string
     *   A message of at most 150 characters that can be used to display to the
     *   user if we know that we are in the backend. Do not display if we might
     *   be on the frontend!
     */
    public function logAndMail(Throwable $e): string
    {
        $message = $this->log->exception($e, true);
        try {
            $this->mailException($e);
        } catch (Throwable) {
        }
        return $this->toAdminMessage($message);
    }

    /**
     * @param string $message
     *   The error message that will be part of the message shown to the admin
     *   user.
     *
     * @return string
     *   A message saying that there was an error and what to do and at most 150
     *   characters of the error message.
     */
    protected function toAdminMessage(string $message): string
    {
        if (function_exists('mb_strlen')) {
            if (mb_strlen($message) > 150) {
                $message = mb_substr($message, 0, 147) . '...';
            }
        } elseif (strlen($message) > 150) {
            $message = substr($message, 0, 147) . '...';
        }
        return sprintf($this->t('crash_admin_message'), $message);
    }

    protected function mailException(Throwable $e): void
    {
        $this->getMail()->createAndSend(['exception' => $e]);
   }

    protected function arrayToList(array $list, bool $isHtml): string
    {
        return $this->util->arrayToList($list, $isHtml, $this->t(...));
    }
}
