<?php

namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Config\Environment;
use Throwable;

class CrashReporter
{
    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /** @var \Siel\Acumulus\Config\Environment */
    protected $environment;

    /** @var \Siel\Acumulus\Helpers\Mailer */
    protected $mailer;

    /**
     * @param \Siel\Acumulus\Helpers\Mailer $mailer
     * @param \Siel\Acumulus\Config\Environment $environment
     * @param \Siel\Acumulus\Helpers\Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(Mailer $mailer, Environment $environment, Translator $translator, Log $log)
    {
        $this->translator = $translator;
        $this->log = $log;
        $this->environment = $environment;
        $this->mailer = $mailer;
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
     * Logs the exception and mails a message to the user.
     *
     * @param \Throwable $e
     *   The error that was thrown.
     *
     * @return string
     *   A message of at most 150 characters that can be used to display to the
     *   user, if we know that we are in the backend. Do not display if we might
     *   be on the frontend!
     */
    public function logAndMail(Throwable $e): string
    {
        $message = $this->log->exception($e);
        $this->mailException($e->__toString());
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
        } else {
            if (strlen($message) > 150) {
                $message = substr($message, 0, 147) . '...';
            }
        }
        return sprintf($this->t('crash_admin_message'), $message);
    }

    protected function mailException(string $errorMessage)
    {
        $environment = $this->environment->get();
        $moduleName = $this->t('module_name');
        $module = $this->t('module');
        $subject = sprintf($this->t('crash_mail_subject'), $moduleName, $module);
        $from = $this->mailer->getFrom();
        $fromName = $this->mailer->getFromName();
        $to = $this->mailer->getTo();
        $support = $environment['supportEmail'];
        $paragraphIntroduction = sprintf($this->t('crash_mail_body_start'), $moduleName, $module, $support);
        $paragraphIntroductionText = wordwrap($paragraphIntroduction, 70);

        $aboutEnvironment = $this->t('about_environment');
        $aboutError = $this->t('about_error');
        $environmentList = $this->environment->getAsLines();
        $environmentListText = $this->arrayToList($environmentList, false);
        $environmentListHtml = $this->arrayToList($environmentList, true);
        $errorMessageHtml = nl2br($errorMessage, false);
        $body = [
            'text' => "$paragraphIntroductionText\n$aboutEnvironment:\n\n$environmentListText\n$aboutError:\n\n$errorMessage\n",
            'html' => "<p>$paragraphIntroduction</p>\n<h3>$aboutEnvironment</h3>\n$environmentListHtml\n<h3>$aboutError</h3>\n<p>$errorMessageHtml</p>\n",
            ];
        $this->mailer->sendMail($from, $fromName, $to, $subject, $body['text'], $body['html']);
    }

    protected function arrayToList(array $list, bool $isHtml): string
    {
        /** @noinspection DuplicatedCode  comes from Form::arrayToList() */
        $result = '';
        if (!empty($list)) {
            foreach ($list as $key => $line) {
                if (is_string($key) && !ctype_digit($key)) {
                    $key = $this->t($key);
                    $line = "$key: $line";
                }
                $result .= $isHtml ? "<li>$line</li>" : "• $line";
                $result .= "\n";
            }
            if ($isHtml) {
                $result = "<ul>$result</ul>";
            }
            $result .= "\n";
        }
        return $result;
    }
}
