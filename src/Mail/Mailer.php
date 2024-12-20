<?php

declare(strict_types=1);

namespace Siel\Acumulus\Mail;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Fld;
use Siel\Acumulus\Helpers\Translator;
use Throwable;

use function count;

/**
 * Mailer allows sending mails.
 *
 * This class is an adapter around the webshops' mail sending feature.
 *
 * The Acumulus plugin might send e-mails to the admin of the webshop/plugin. It normally
 * only does so when errors occurred during sending transactions to Acumulus, but to be
 * able to give support, this might be forced by configuration or when using test mode.
 *
 * Normally, using code should call the {@see sendAdminMail()} method, that fetches the
 * 'from' and (adminstrators') 'to' addresses itself.
 * If other (non admin) e-mails need to be sent, just use the {@see sendMail()} method.
 */
abstract class Mailer
{
    protected Config $config;
    protected Environment $environment;
    protected Translator $translator;
    private array $mailsSent = [];

    public function __construct(Config $config, Environment $environment, Translator $translator)
    {
        $this->config = $config;
        $this->environment = $environment;
        $this->translator = $translator;
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

    /**
     * Sends an email.
     *
     * @return mixed
     *   Success (true); error message, result (hopefully Stringable), Throwable or just
     *   false otherwise.
     */
    public function sendAdminMail(string $subject, string $bodyText, string $bodyHtml): mixed
    {
        return $this->sendMail($this->getFrom(), $this->getFromName(), $this->getTo(), $subject, $bodyText, $bodyHtml);
    }

    /**
     * Sends an email.
     *
     * @return mixed
     *   Success (true); error message, result (hopefully Stringable), Throwable or just
     *   false otherwise.
     */
    public function sendMail(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): mixed
    {
        try {
            $this->mailsSent[] = compact('from', 'fromName', 'to', 'subject', 'bodyText', 'bodyHtml');
            return $this->send($from, $fromName, $to, $subject, $bodyText, $bodyHtml);
        } catch (Throwable $e) {
            // We do not log here, we have extensive logging per specific mail
            return $e;
        }
    }

    /**
     * Sends the email via the host system mail feature.
     *
     * @return mixed
     *   Success (true); error message, result (hopefully Stringable), Throwable or just
     *   false otherwise.
     */
    abstract protected function send(string $from, string $fromName, string $to, string $subject, string $bodyText, string $bodyHtml): mixed;

    /**
     * Returns the mail from address.
     *
     * This base implementation returns 'webshop@<hostname>' which may not be in use and
     * fail due to SPF or DKIM settings.
     *
     * Webshops should override, as all webshops will have a configured e-mail address
     * to use to send order confirmations, etc.
     */
    public function getFrom(): string
    {
        return 'webshop@' . $this->environment->get('hostName');
    }

    /**
     * Returns the mail from name.
     *
     * Webshops should override this method when they have a nice name that goes with the
     * {@see getFrom()} address.
     */
    public function getFromName(): string
    {
        return $this->t('mail_sender_name');
    }

    /**
     * Returns the mail to address.
     *
     * This base implementation returns (first non-empty):
     * - The configured 'emailonerror' address, which normally is exactly what we want but
     *   will be empty if not yet set.
     * - The {@See getFrom()} address, but this may be a "no-reply" address.
     *
     * So web shops should override this method when they can return a better alternative
     * than the getFrom() address.
     */
    public function getTo(): string
    {
        $to = $this->config->getCredentials()[Fld::EmailOnError];
        if (empty($to)) {
            $to = $this->getFrom();
        }
        return $to;
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
