<?php
namespace Siel\Acumulus\Helpers;

use Exception;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Invoice\InvoiceAddResult;
use Siel\Acumulus\Tag;

/**
 * Mailer allows sending mails.
 *
 * This abstract base class defines functionality to create a mail that
 * communicates the result of sending invoice data to Acumulus (method
 * Mailer::sendInvoiceAddMailResult). It must be overridden per web shop to
 * define the bridge between this library and the web shop's mail subsystem.
 *
 * If you want to send other mails, just use the Mailer::sendMail() method.
 */
abstract class Mailer
{
    /** @var \Siel\Acumulus\Config\Config */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    public function __construct(Config $config, Translator $translator, Log $log)
    {
        $this->config = $config;
        $this->log = $log;

        $this->translator = $translator;
        $translations = new MailTranslations();
        $this->translator->add($translations);
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
     *   Success (true); error message, error object or just false otherwise.
     */
    abstract public function sendMail(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $bodyText,
        string $bodyHtml
    );

    /**
     * Sends an email with the results of sending an invoice to Acumulus.
     * The mail is sent to the shop administrator ('emailonerror' setting).
     */
    public function sendInvoiceAddMailResult(InvoiceAddResult $invoiceSendResult, string $invoiceSourceType, string $invoiceSourceReference): bool
    {
        $from = $this->getFrom();
        $fromName = $this->getFromName();
        $to = $this->getTo();
        $subject = $this->getSubject($invoiceSendResult);
        $content = $this->getBody($invoiceSendResult , $invoiceSourceType, $invoiceSourceReference);

        $logMessage = sprintf('Mailer::sendMail("%s", "%s", "%s", "%s")', $from, $fromName, $to, $subject);
        $result = $this->sendMail($from, $fromName, $to, $subject, $content['text'], $content['html']);
        if ($result !== true) {
            if ($result === false) {
                $message = 'false';
            } elseif ($result === null) {
                $message = 'null';
            } elseif ($result instanceof Exception) {
                $message = $result->getMessage();
            } elseif (!is_string($result)) {
                $message = print_r($result, true);
            } else {
                $message = $result;
            }
            $this->log->error('%s: failed: %s', $logMessage, $message);
        } else {
            $this->log->info('%s: success', $logMessage);
        }

        return $result === true;
    }

    /**
     * Returns the mail from address.
     *
     * This base implementation returns 'webshop@<hostname>'.
     */
    protected function getFrom(): string
    {
        $env = $this->config->getEnvironment();
        return 'webshop@' . $env['hostName'];
    }

    /**
     * Returns the mail from name.
     */
    protected function getFromName(): string
    {
        return $this->t('mail_sender_name');
    }

    /**
     * Returns the mail to address.
     *
     * This base implementation returns the configured 'emailonerror' address,
     * which normally is exactly what we want.
     */
    protected function getTo(): string
    {
        $credentials = $this->config->getCredentials();
        if (isset($credentials[Tag::EmailOnError])) {
            return $credentials[Tag::EmailOnError];
        }
        $env = $this->config->getEnvironment();
        return 'webshop@' . $env['hostName'];
    }

    /**
     * Returns the subject for the mail.
     *
     * The subject depends on:
     * - the result status.
     * - whether the invoice was sent in test mode.
     * - whether the invoice was sent as concept.
     * - the emailAsPdf setting.
     */
    protected function getSubject(InvoiceAddResult $invoiceSendResult): string
    {
        $pluginSettings = $this->config->getPluginSettings();
        $isTestMode = $pluginSettings['debug'] === Config::Send_TestMode;
        $resultInvoice = $invoiceSendResult->getResponse();
        $isConcept = !$invoiceSendResult->hasError() && empty($resultInvoice['entryid']);

        $subjectBase = 'mail_subject';
        if ($isTestMode) {
            $subjectBase .= '_test_mode';
        } elseif ($isConcept) {
            $subjectBase .= '_concept';
        }
        $subject = $this->t($subjectBase);

        $subjectResult = 'mail_subject';
        switch ($invoiceSendResult->getStatus()) {
            case Severity::Exception:
                $subjectResult .= '_exception';
                break;
            case Severity::Error:
                $subjectResult .= '_error';
                break;
            case Severity::Warning:
                $subjectResult .= '_warning';
                break;
            case Severity::Success:
            default:
                $subjectResult .= '_success';
                break;
        }
        $subject .= ': ' . $this->t($subjectResult);

        if ($isTestMode || $isConcept || $invoiceSendResult->hasError()) {
            $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
            if ($emailAsPdfSettings['emailAsPdf']) {
                // Normally, Acumulus will send a pdf to the client, but due to
                // 1 of the conditions above this was not done.
                $subject .= ', ' . $this->t('mail_subject_no_pdf');
            }
        }

        return $subject;
    }

    /**
     * Returns the mail body as text and as HTML.
     *
     * @return string[]
     *   An array with the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getBody(InvoiceAddResult $result, string $invoiceSourceType, string $invoiceSourceReference): array
    {
        $resultInvoice = $result->getResponse();
        $bodyTexts = $this->getStatusSpecificBody($result);
        $messagesTexts = $this->getMessages($result);
        $supportTexts = $this->getSupportMessages($result);
        $replacements = [
            '{invoice_source_type}' => $this->t($invoiceSourceType),
            '{invoice_source_reference}' => $invoiceSourceReference,
            '{acumulus_invoice_id}' => $resultInvoice['invoicenumber'] ?? $this->t('message_no_invoice'),
            '{status}' => $result->getStatus(),
            '{status_message}' => $result->getStatusText(),
            '{status_specific_text}' => $bodyTexts['text'],
            '{status_specific_html}' => $bodyTexts['html'],
            '{messages_text}' => $messagesTexts['text'],
            '{messages_html}' => $messagesTexts['html'],
            '{support_messages_text}' => $supportTexts['text'],
            '{support_messages_html}' => $supportTexts['html'],
        ];
        $text = $this->t('mail_text');
        $text = strtr($text, $replacements);
        $html = $this->t('mail_html');
        $html = strtr($html, $replacements);
        return ['text' => $text, 'html' => $html];
    }

    /**
     * Returns the status specific part of the body for the mail.
     *
     * This body part depends on:
     * - the result status.
     * - whether the invoice was sent in test mode
     * - whether the invoice was sent as concept
     * - the emailAsPdf setting
     *
     * @param \Siel\Acumulus\Invoice\InvoiceAddResult $invoiceSendResult
     *
     * @return string[]
     *   An array with the status specific part of the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getStatusSpecificBody(InvoiceAddResult $invoiceSendResult): array
    {
        $pluginSettings = $this->config->getPluginSettings();
        $isTestMode = $pluginSettings['debug'] === Config::Send_TestMode;
        $resultInvoice = $invoiceSendResult->getResponse();
        // @refactor: can be taken from invoice array if that would be part of the Result
        $isConcept = !$invoiceSendResult->hasError() && empty($resultInvoice['entryid']);
        $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
        $isEmailAsPdf = (bool) $emailAsPdfSettings['emailAsPdf'];

        // Collect the messages.
        $sentences = [];
        switch ($invoiceSendResult->getStatus()) {
            case Severity::Exception:
                $sentences[] = 'mail_body_exception';
                // @todo: check this: we want to know what was set just before executing the curl request.
                $sentences[] = $invoiceSendResult->getHttpResponse() !== null
                    ? 'mail_body_exception_invoice_maybe_created'
                    : 'mail_body_exception_invoice_not_created';
                break;
            case Severity::Error:
                $sentences[] = 'mail_body_errors';
                $sentences[] = 'mail_body_errors_not_created';
                if ($isEmailAsPdf) {
                    $sentences[] = 'mail_body_pdf_enabled';
                    $sentences[] = 'mail_body_pdf_not_sent_errors';
                }
                break;
            case Severity::Warning:
                $sentences[] = 'mail_body_warnings';
                if ($isTestMode) {
                    $sentences[] = 'mail_body_testmode';
                } elseif ($isConcept) {
                    $sentences[] = 'mail_body_concept';
                    if ($isEmailAsPdf) {
                        $sentences[] = 'mail_body_pdf_enabled';
                        $sentences[] = 'mail_body_pdf_not_sent_concept';
                    }
                } else {
                    $sentences[] = 'mail_body_warnings_created';
                }
                break;
            case Severity::Success:
            default:
                $sentences[] = 'mail_body_success';
                if ($isTestMode) {
                    $sentences[] = 'mail_body_testmode';
                } elseif ($isConcept) {
                    $sentences[] = 'mail_body_concept';
                    if ($isEmailAsPdf) {
                        $sentences[] = 'mail_body_pdf_enabled';
                        $sentences[] = 'mail_body_pdf_not_sent_concept';
                    }
                }
                break;
        }

        // Translate the messages.
        foreach ($sentences as &$sentence) {
            $sentence = $this->t($sentence);
        }

        // Collapse and format the sentences.
        $sentences = implode(' ', $sentences);

        return [
            'text' => wordwrap($sentences, 70),
            'html' => "<p>$sentences</p>",
        ];
    }

    /**
     * Returns the messages along with some descriptive text.
     *
     * @return string[]
     *   An array with the messages part of the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getMessages(InvoiceAddResult $result): array
    {
        $messages = [
            'text' => '',
            'html' => '',
        ];

        if ($result->hasRealMessages()) {
            $header = $this->t('mail_messages_header');
            $description = $this->t('mail_messages_desc');
            $descriptionHtml = $this->t('mail_messages_desc_html');
            $messagesText = $result->formatMessages(Message::Format_PlainListWithSeverity, Severity::RealMessages);
            $messagesHtml = $result->formatMessages(Message::Format_HtmlListWithSeverity, Severity::RealMessages);
            $messages = [
                'text' => "\n$header\n\n$messagesText\n\n$description\n",
                'html' => "<details open><summary>$header</summary>$messagesHtml<p>$descriptionHtml</p></details>",
            ];
        }
        return $messages;
    }

    /**
     * Returns the support messages along with some descriptive text.
     *
     * @return string[]
     *   An array with the support messages part of the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getSupportMessages(InvoiceAddResult $result): array
    {
        $messages = [
            'text' => '',
            'html' => '',
        ];

        $pluginSettings = $this->config->getPluginSettings();
        // We add the request and response messages when set so or if there were
        // warnings or severer messages, thus not with notices.
        $addReqResp = $pluginSettings['debug'] === Config::Send_SendAndMailOnError ? InvoiceAddResult::AddReqResp_WithOther : InvoiceAddResult::AddReqResp_Always;
        if ($addReqResp === InvoiceAddResult::AddReqResp_Always || $result->getStatus() >= Severity::Warning) {
            $logMessages = new MessageCollection($this->translator);
            foreach ($result->toLogMessages(false) as $message) {
                $logMessages->createAndAdd($message, Severity::Log);
            }
            if (!empty($logMessages->getMessages())) {
                $header = $this->t('mail_support_header');
                $description = $this->t('mail_support_desc');
                $supportMessagesText = $logMessages->formatMessages(Message::Format_PlainList);
                $supportMessagesHtml = $logMessages->formatMessages(Message::Format_HtmlList);
                $messages = [
                    'text' => "\n$header\n\n$description\n\n$supportMessagesText\n",
                    'html' => "<details><summary>$header</summary><p>$description</p>$supportMessagesHtml</details>",
                ];
            }
        }
        return $messages;
    }
}
