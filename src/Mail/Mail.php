<?php

declare(strict_types=1);

namespace Siel\Acumulus\Mail;

use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\Environment;
use Siel\Acumulus\Helpers\Log;
use Siel\Acumulus\Helpers\Message;
use Siel\Acumulus\Helpers\MessageCollection;
use Siel\Acumulus\Helpers\Result;
use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\WrapperInterface;
use Stringable;
use Throwable;

use function is_array;
use function is_string;
use function sprintf;
use function strlen;

/**
 * Mail allows to create and send admin mails.
 *
 * This abstract base class serves as a template with some boilerplate code that can be
 * used by actual mail templates.
 */
abstract class Mail
{
    private Mailer $mailer;
    private Config $config;
    private Environment $environment;
    protected Translator $translator;
    private Log $log;
    protected array $translatedPlaceholders;
    protected array $args;

    public function __construct(Mailer $mailer, Config $config, Environment $environment, Translator $translator, Log $log)
    {
        $this->mailer = $mailer;
        $this->config = $config;
        $this->environment = $environment;
        $this->log = $log;
        $this->translator = $translator;
        $this->translator->add(new MailTranslations());
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

    protected function getMailer(): Mailer
    {
        return $this->mailer;
    }

    protected function getConfig(): Config
    {
        return $this->config;
    }

    protected function getLog(): Log
    {
        return $this->log;
    }

    protected function getEnvironment(): Environment
    {
        return $this->environment;
    }

    protected function getResult(): ?Result
    {
        return $this->args['result'] ?? null;
    }

    protected function getSource(): ?WrapperInterface
    {
        return $this->args['source'] ?? null;
    }

    /**
     * Sends an email with the results of sending an invoice to Acumulus.
     * The mail is sent to the shop administrator ('emailonerror' setting).
     */
    public function createAndSend(array $args): bool
    {
        $this->args = $args;

        $this->translatedPlaceholders = $this->getPlaceholders();
        $subject = strtr($this->getSubject(), $this->translatedPlaceholders);
        $content = $this->getBody();
        $content['text'] = strtr($content['text'], $this->translatedPlaceholders);
        $content['html'] = strtr($content['html'], $this->translatedPlaceholders);

        $logMessage = sprintf('Mail::send("%s")', $subject);
        $result = $this->send($subject, $content['text'], $content['html']);
        if ($result !== true) {
            if ($result === false) {
                $message = 'false';
            } elseif ($result instanceof Throwable) {
                $message = $result->getMessage();
            } elseif (is_string($result) || $result instanceof Stringable) {
                $message = (string) $result;
            } else {
                $message = print_r($result, true);
            }
            $this->log->error('%s: failed: %s', $logMessage, $message);
        } else {
            $this->log->info('%s: success', $logMessage);
        }
        return $result === true;
    }

    /**
     * Sends an email.
     *
     * @return mixed
     *   Success (true); error message, result (hopefully Stringable), Throwable or just
     *   false otherwise.
     */
    protected function send(string $subject, string $bodyText, string $bodyHtml): mixed
    {
        return $this->getMailer()->sendAdminMail($subject, $bodyText, $bodyHtml);
    }

    /**
     * Returns a list of placeholders and their value.
     *
     * NOTE: This method is only called once before construction of the mail text
     *   starts, because the results are passed through a translation phase.
     *
     * @return string[]
     */
    protected function getPlaceholders(): array
    {
        return [
            '{shop}' => $this->t('shop'),
            '{module_name}' => $this->t('module_name'),
            '{module}' => $this->t('module'),
            '{support_mail}' => $this->getEnvironment()->get('supportEmail'),
            '{source_label}' => $this->getSource()?->getLabel(MB_CASE_TITLE) ?? '',
            '{source_reference}' => $this->getSource()?->getReference() ?? '',
            '{severity}' => $this->getResult()?->getSeverity() ?? Severity::Exception,
            '{status_text}' => $this->getResult()?->getAcumulusResult()?->getStatusText() ?? $this->t('action_not_sent'),
        ];
    }

    /**
     * Returns the subject for the mail.
     *
     * The subject is based on:
     * - A base part: see {@see Mail::getSubjectBase()}.
     * - A phrase describing the result: see {@see Mail::getSubjectResult()}.
     */
    protected function getSubject(): string
    {
        $subject = $this->getSubjectBase();
        $subjectResultPhrase = $this->getSubjectResult();
        if ($subjectResultPhrase !== '') {
            $subject .= ": $subjectResultPhrase";
        }
        return $subject;
    }

    /**
     * Returns a base phrase for the subject of the mail.
     *
     * This is a short description of what was sent and if it was in test mode which mode:
     * - whether the invoice was sent in test mode.
     * - whether the invoice was sent as concept.
     */
    protected function getSubjectBase(): string
    {
        $subjectBase = $this->t('mail_subject');
        if ($this->getResult()?->isTestMode() ?? false) {
            $subjectBase .= $this->t('mail_subject_test_mode');
        }
        return $subjectBase;
    }

    /**
     * Gets a translated phrase describing the result based on severity (from success to
     * exception). Will be empty if there is no {@see getResult() result}.
     */
    protected function getSubjectResult(): string
    {
        $subjectResult = 'mail_subject' . match ($this->getResult()?->getSeverity()) {
                Severity::Exception => '_exception',
                Severity::Error => '_error',
                Severity::Warning => '_warning',
                null => '_null',
                default => '_success',
            };
        return $this->t($subjectResult);
    }

    /**
     * Returns the mail body as text and as HTML.
     *
     * @return string[]
     *   An array with the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getBody(): array
    {
        return $this->concatenateTextTuples(
            $this->getIntro(),
            $this->getAbout(),
            $this->getMessages(),
            $this->getSupport(),
        );
    }

    /**
     * Returns the status specific part of the body for the mail.
     *
     * This body part depends on:
     * - the result status.
     * - whether the invoice was sent in test mode
     *
     * @return string[]
     *   An array with the status specific part of the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getIntro(): array
    {
        return $this->toParagraph($this->getIntroSentences());
    }

    /**
     * Returns an array of sentences that form the main paragraph of the mail.
     */
    protected function getIntroSentences(): array
    {
        // Collect the sentences.
        $sentences = [];
        if ($this->getResult() === null) {
            $sentences[] = 'mail_body_intro_crash';
            $sentences[] = 'mail_body_intro_temporary';
            $sentences[] = 'mail_body_intro_contact_support';
            $sentences[] = 'mail_body_intro_forward_all';
        } else {
            switch ($this->getResult()?->getSeverity()) {
                case Severity::Exception:
                    $sentences[] = 'mail_body_exception';
                    $sentences[] = $this->getResult()?->getAcumulusResult()?->getHttpResponse() !== null
                        ? 'mail_body_exception_maybe_created'
                        : 'mail_body_exception_not_created';
                    break;
                case Severity::Error:
                    $sentences[] = 'mail_body_errors';
                    $sentences[] = 'mail_body_errors_not_created';
                    break;
                case Severity::Warning:
                    $sentences[] = 'mail_body_warnings';
                    $sentences[] = ($this->getResult()?->isTestMode() ?? false)
                        ? 'mail_body_test_mode'
                        : 'mail_body_warnings_created';
                    break;
                default:
                    // Other severities which basically indicate success but possibly
                    // with "informational"  messages.
                    $sentences[] = 'mail_body_success';
                    if ($this->getResult()?->isTestMode() ?? false) {
                        $sentences[] = 'mail_body_test_mode';
                    }
                    break;
            }
        }
        return $sentences;
    }

    /**
     * Description.
     */
    protected function getAbout(): array
    {
        return $this->concatenateTextTuples(
            $this->toHeader('mail_about_header'),
            $this->toTable($this->getAboutLines())
        );
    }

    protected function getAboutLines(): array
    {
        $lines = [];
        if ($this->getResult() instanceof Result) {
            $lines += [
                'send_status' => rtrim('{severity} - "{status_text}"'),
            ];
        }
        return $lines;
    }

    /**
     * Returns the messages along with some descriptive text.
     *
     * @return string[]
     *   An array with the messages part of the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getMessages(): array
    {
        $result = $this->getResult();
        if ($result instanceof Result) {
            if ($result->hasRealMessages()) {
                return $this->concatenateTextTuples(
                    $this->toHeader('mail_messages_header'),
                    $this->toDetails($this->getMessageList()),
                    $this->getMessageDesc()
                );
            }
        }
        return [];
    }

    protected function getMessageList(): array
    {
        /** @noinspection NullPointerExceptionInspection  Only called by getMessages() that checked getResult() */
        return [
            'text' => $this->getResult()->formatMessages(Message::Format_PlainListWithSeverity, Severity::RealMessages),
            'html' => $this->getResult()->formatMessages(Message::Format_HtmlListWithSeverity, Severity::RealMessages),
        ];
    }

    protected function getMessageDesc(): array
    {
        return [
            'text' => $this->t('mail_messages_desc_text') . "\n",
            'html' => '<p>' . $this->t('mail_messages_desc_html') . "</p>\n",
        ];
    }

    /**
     * Returns the support messages along with some descriptive text.
     *
     * @return string[]
     *   An array with the support messages part of the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getSupport(): array
    {
        if ($this->doAddSupport()) {
            return $this->concatenateTextTuples(
                $this->toHeader('mail_support_header'),
                $this->getSupportDesc(),
                $this->toDetails($this->getSupportMessageList())
            );
        }
        return [];
    }

    protected function doAddSupport(): bool
    {
        $pluginSettings = $this->getConfig()->getPluginSettings();
        // We add the request and response messages when set so, or if there were
        // warnings or worse messages, thus not with notices.
        $addReqResp = $pluginSettings['debug'] === Config::Send_SendAndMailOnError
            ? Result::AddReqResp_WithOther
            : Result::AddReqResp_Always;
        $result = $this->getResult();
        $acumulusResult = $result?->getAcumulusResult();
        return $result !== null
            && $acumulusResult !== null
            && ($result->hasRealMessages() || $addReqResp === Result::AddReqResp_Always);
    }

    protected function getSupportDesc(): array
    {
        return $this->toParagraph(['mail_support_desc', 'mail_support_contact']);
    }

    protected function getSupportMessageList(): array
    {
        /**
         * @var \Siel\Acumulus\ApiClient\AcumulusResult $acumulusResult
         * @noinspection NullPointerExceptionInspection existence checked in doAddSupport()
         */
        $acumulusResult = $this->getResult()->getAcumulusResult();
        $logMessages = new MessageCollection($this->translator);
        $logMessages->createAndAddMessage($acumulusResult->getAcumulusRequest()->getMaskedRequest(), Severity::Log);
        $logMessages->createAndAddMessage($acumulusResult->getMaskedResponse(), Severity::Log);
        return [
            'text' => $logMessages->formatMessages(Message::Format_PlainList),
            'html' => $logMessages->formatMessages(Message::Format_HtmlList),
        ];
    }

    protected function toHeader(string $phrase): array
    {
        $phrase = $this->t($phrase);
        return [
            'text' => "$phrase:\n",
            'html' => '<h3>' . htmlspecialchars($phrase, ENT_NOQUOTES) . "</h3>\n",
        ];
    }

    /**
     * Creates a paragrpah from the sentences.
     *
     * @param string|array $sentences
     *   1 or more sentences that form a paragraph.
     *
     * @return string[]
     *   Description.
     */
    protected function toParagraph(string|array $sentences): array
    {
        $sentences = $this->replacePlaceholders($this->translatePhrases((array) $sentences));
        return [
            'text' => wordwrap(implode(' ', $sentences), 70) . "\n",
            'html' => '<p>' . htmlspecialchars(implode("\n", $sentences), ENT_NOQUOTES) . "</p>\n",
        ];
    }

    protected function toDetails(array $phrase): array
    {
        return [
            'text' => $phrase['text'],
            'html' => sprintf("<details><summary>%s</summary>%s</details>\n", $this->t('click_to_toggle'), $phrase['html']),
        ];
    }

    protected function toTable(array $rows): array
    {
        $tableRows = [];
        $maxLabelLength = 0;
        foreach ($rows as $label => $value) {
            // We need to translate and replace placeholders here, to get the correct
            // maximum header length.
            $tableHeader = $this->t(strtr($label, $this->translatedPlaceholders));
            $maxLabelLength = max($maxLabelLength, strlen($tableHeader) + 2);
            $tableCell = $this->t(strtr($value, $this->translatedPlaceholders));
            $tableRows[$tableHeader] = $tableCell;
        }
        $tableText = '';
        $tableHtml = "<table style=\"text-align: left;\">\n";
        $rowFormatText = "%-{$maxLabelLength}s%s\n";
        $rowFormatHtml = "<tr><th>%s</th><td>%s</td></tr>\n";
        foreach ($tableRows as $header => $value) {
            $strippedValue = strip_tags($value);
            $tableText .= sprintf($rowFormatText, $header . ':', $strippedValue);
            $tableHtml .= sprintf(
                $rowFormatHtml,
                htmlspecialchars($header, ENT_NOQUOTES),
                $strippedValue !== $value ? $value : htmlspecialchars($value, ENT_NOQUOTES)
            );
        }
        $tableHtml .= "</table>\n";
        return [
            'text' => $tableText,
            'html' => $tableHtml,
        ];
    }

    protected function translatePhrases(array $phrases): array
    {
        return array_map(function (string $phrase) {
            return $this->t($phrase);
        }, $phrases);
    }

    protected function replacePlaceholders(array $phrases): array
    {
        return array_map(function (string $phrase) {
            return strtr($phrase, $this->translatedPlaceholders);
        }, $phrases);
    }

    /**
     * Concatenates the 'text' and 'html' entries of a set of arrays
     *
     * @param string[] ...$textTuples
     *   Arrays with keys 'text' and 'html' which may be empty or absent
     *
     * @return string[]
     *   An array with keys 'text' and 'html', containing the concatenation of all the
     *  'text' resp. 'html' entries of the given arrays.
     */
    protected function concatenateTextTuples(array ...$textTuples): array
    {
        return [
            'text' => implode("\n", array_filter(array_column($textTuples, 'text'))),
            'html' => implode('', array_column($textTuples, 'html')),
        ];
    }
}
