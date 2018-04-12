<?php
namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Tag;
use Siel\Acumulus\Web\Result;

/**
 * Class Mailer allows to send mails. This class should be overridden per shop
 * to use the shop provided mailing features.
 */
abstract class Mailer
{
    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\Translator */
    protected $translator;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /**
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param Translator $translator
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(ConfigInterface $config, Translator $translator, Log $log)
    {
        $this->log = $log;
        $this->config = $config;

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
    protected function t($key)
    {
        return $this->translator->get($key);
    }

    /**
     * Sends an email with the results of a sent invoice.
     *
     * The mail is sent to the shop administrator (emailonerror setting).
     *
     * @param \Siel\Acumulus\Web\Result $invoiceSendResult
     * @param string $invoiceSourceType
     * @param string $invoiceSourceReference
     *
     * @return bool
     *   Success.
     */
    public function sendInvoiceAddMailResult(Result $invoiceSendResult, $invoiceSourceType, $invoiceSourceReference)
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
            } else  if ($result === null) {
                $message = 'null';
            } else  if ($result instanceof \Exception) {
                $message = $result->getMessage();
            } else  if (!is_string($result)) {
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
     * Sends an email.
     *
     * @param string $from
     * @param string $fromName
     * @param string $to
     * @param string $subject
     * @param string $bodyText
     * @param string $bodyHtml
     *
     * @return mixed
     *   Success (true); error message, error object or just false otherwise.
     */
    abstract public function sendMail($from, $fromName, $to, $subject, $bodyText, $bodyHtml);

    /**
     * Returns the mail from address.
     *
     * @return string
     */
    abstract protected function getFrom();

    /**
     * Returns the mail from name.
     *
     * @return string
     */
    protected function getFromName()
    {
        return $this->t('mail_sender_name');
    }

    /**
     *  Returns the mail to address.
     *
     * @return string
     */
    protected function getTo()
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
     * - whether the invoice was sent in test mode
     * - whether the invoice was sent as concept
     * - the emailAsPdf setting
     *
     * @param \Siel\Acumulus\Web\Result $invoiceSendResult
     *
     * @return string
     */
    protected function getSubject(Result $invoiceSendResult)
    {
        $pluginSettings = $this->config->getPluginSettings();
        $isTestMode = $pluginSettings['debug'] === PluginConfig::Send_TestMode;
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
            case Result::Status_Exception:
                $subjectResult .= '_exception';
                break;
            case Result::Status_Errors:
                $subjectResult .= '_error';
                break;
            case Result::Status_Warnings:
                $subjectResult .= '_warning';
                break;
            case Result::Status_Success:
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
     * Returns the body for the mail.
     *
     * The body depends on:
     * - the result status.
     * - the value of isSent (in the result object)
     * - whether the invoice was sent in test mode
     * - whether the invoice was sent as concept
     * - the emailAsPdf setting
     *
     * @param \Siel\Acumulus\Web\Result $invoiceSendResult
     *
     * @return string[]
     */
    protected function getStatusSpecificBody(Result $invoiceSendResult)
    {
        $pluginSettings = $this->config->getPluginSettings();
        $isTestMode = $pluginSettings['debug'] === PluginConfig::Send_TestMode;
        $resultInvoice = $invoiceSendResult->getResponse();
        // @todo: can be taken from invoice array if that would be part of the Result
        $isConcept = !$invoiceSendResult->hasError() && empty($resultInvoice['entryid']);
        $emailAsPdfSettings = $this->config->getEmailAsPdfSettings();
        $isEmailAsPdf = (bool) $emailAsPdfSettings['emailAsPdf'];

        // Collect the messages.
        $sentences = array();
        switch ($invoiceSendResult->getStatus()) {
            case Result::Status_Exception:
                $sentences[] = 'mail_body_exception';
                $sentences[] = $invoiceSendResult->isSent() ? 'mail_body_exception_invoice_maybe_created' : 'mail_body_exception_invoice_not_created';
                break;
            case Result::Status_Errors:
                $sentences[] = 'mail_body_errors';
                $sentences[] = 'mail_body_errors_not_created';
                if ($isEmailAsPdf) {
                    $sentences[] = 'mail_body_pdf_enabled';
                    $sentences[] = 'mail_body_pdf_not_sent_errors';
                }
                break;
            case Result::Status_Warnings:
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
            case Result::Status_Success:
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
        $texts = array(
            'text' => wordwrap($sentences, 70),
            'html' => "<p>$sentences</p>",
        );
        return $texts;
    }

    /**
     * Returns the  messages along with some descriptive text.
     *
     * @param \Siel\Acumulus\Web\Result $result
     *
     * @return string[]
     *   An array with a plain text (key='text') and an html string (key='html')
     *   containing the messages with some descriptive text.
     */
    protected function getMessages(Result $result)
    {
        $messages = array(
            'text' => '',
            'html' => '',
        );

        if ($result->hasMessages()) {
            $header = $this->t('mail_messages_header');
            $description = $this->t('mail_messages_desc');
            $descriptionHtml = $this->t('mail_messages_desc_html');
            $messagesText = $result->getMessages(Result::Format_FormattedText);
            $messagesHtml = $result->getMessages(Result::Format_Html);
            $messages = array(
                'text' => "\n$header\n\n$messagesText\n\n$description\n",
                'html' => "<details open><summary>$header</summary>$messagesHtml<p>$descriptionHtml</p></details>",
            );
        }
        return $messages;
    }

    /**
     * Returns the support messages along with some descriptive text.
     *
     * @param \Siel\Acumulus\Web\Result $result
     *
     * @return string[]
     *   An array with a plain text (key='text') and an html string (key='html')
     *   containing the support messages with some descriptive text.
     */
    protected function getSupportMessages(Result $result)
    {
        $messages = array(
            'text' => '',
            'html' => '',
        );

        $pluginSettings = $this->config->getPluginSettings();
        // We add the request and response messages when set so or if there were
        // warnings or severer messages, thus not with notices.
        $addReqResp = $pluginSettings['debug'] === PluginConfig::Send_SendAndMailOnError ? Result::AddReqResp_WithOther : Result::AddReqResp_Always;
        if ($addReqResp === Result::AddReqResp_Always || ($addReqResp === Result::AddReqResp_WithOther && $result->getStatus() >= Result::Status_Warnings)) {
            if ($result->getRawRequest() !== null || $result->getRawResponse() !== null) {
                $header = $this->t('mail_support_header');
                $description = $this->t('mail_support_desc');
                $supportMessagesText = $result->getRawRequestResponse(Result::Format_FormattedText);
                $supportMessagesHtml = $result->getRawRequestResponse(Result::Format_Html);
                $messages = array(
                    'text' => "\n$header\n\n$description\n\n$supportMessagesText\n",
                    'html' => "<details><summary>$header</summary><p>$description</p>$supportMessagesHtml</details>",
                );
            }
        }
        return $messages;
    }

    /**
     * Returns the mail body as text and as html.
     *
     * @param \Siel\Acumulus\Web\Result $result
     * @param string $invoiceSourceType
     * @param string $invoiceSourceReference
     *
     * @return string[]
     *   An array with keys text and html.
     */
    protected function getBody(Result $result, $invoiceSourceType, $invoiceSourceReference)
    {
        $resultInvoice = $result->getResponse();
        $bodyTexts = $this->getStatusSpecificBody($result);
        $supportTexts = $this->getSupportMessages($result);
        $messagesTexts = $this->getMessages($result);
        $replacements = array(
            '{invoice_source_type}' => $this->t($invoiceSourceType),
            '{invoice_source_reference}' => $invoiceSourceReference,
            '{acumulus_invoice_id}' => isset($resultInvoice['invoicenumber']) ? $resultInvoice['invoicenumber'] : $this->t('message_no_invoice'),
            '{status}' => $result->getStatus(),
            '{status_message}' => $result->getStatusText(),
            '{status_specific_text}' => $bodyTexts['text'],
            '{status_specific_html}' => $bodyTexts['html'],
            '{messages_text}' => $messagesTexts['text'],
            '{messages_html}' => $messagesTexts['html'],
            '{support_messages_text}' => $supportTexts['text'],
            '{support_messages_html}' => $supportTexts['html'],
        );
        $text = $this->t('mail_text');
        $text = strtr($text, $replacements);
        $html = $this->t('mail_html');
        $html = strtr($html, $replacements);
        return array('text' => $text, 'html' => $html);
    }
}
