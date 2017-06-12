<?php
namespace Siel\Acumulus\Helpers;

use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\PluginConfig;
use Siel\Acumulus\Web\Service;

/**
 * Class Mailer allows to send mails. This class should be overridden per shop
 * to use the shop provided mailing features.
 */
abstract class Mailer
{
    /** @var \Siel\Acumulus\Config\ConfigInterface */
    protected $config;

    /** @var \Siel\Acumulus\Helpers\TranslatorInterface */
    protected $translator;

    /** @var \Siel\Acumulus\Web\Service */
    protected $service;

    /** @var \Siel\Acumulus\Helpers\Log */
    protected $log;

    /**
     * @param \Siel\Acumulus\Config\ConfigInterface $config
     * @param TranslatorInterface $translator
     * @param \Siel\Acumulus\Web\Service $service
     * @param \Siel\Acumulus\Helpers\Log $log
     */
    public function __construct(ConfigInterface $config, TranslatorInterface $translator, Service $service, Log $log)
    {
        $this->log = $log;
        $this->config = $config;
        $this->service = $service;

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
     * @param array $result
     * @param string[] $messages
     * @param string $invoiceSourceType
     * @param string $invoiceSourceReference
     *
     * @return bool
     *   Success.
     */
    public function sendInvoiceAddMailResult(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference)
    {
        $from = $this->getFrom();
        $fromName = $this->getFromName();
        $to = $this->getTo();
        $subject = $this->getSubject($result);
        $content = $this->getBody($result, $messages, $invoiceSourceType, $invoiceSourceReference);

        $this->log->info('Mailer::sendMail("%s", "%s", "%s", "%s") with body = %s', $from, $fromName, $to, $subject, $content['text']);

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
            $this->log->error('Mailer::sendInvoiceAddMailResult(): failed: %s', $message);
        } else {
            $this->log->info('Mailer::sendInvoiceAddMailResult(): success');
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
        if (isset($credentials['emailonerror'])) {
            return $credentials['emailonerror'];
        }
        $env = $this->config->getEnvironment();
        return 'webshop@' . $env['hostName'];
    }

    /**
     * Returns the subject for the mail.
     *
     * The subject depends on the result status.
     *
     * @param array $result
     *
     * @return string
     */
    protected function getSubject(array $result)
    {
        switch ($result['status']) {
            case PluginConfig::Status_Exception:
            case PluginConfig::Status_Errors:
                return $this->t('mail_subject_errors');
            case PluginConfig::Status_Warnings:
                return $this->t('mail_subject_warnings');
            case PluginConfig::Status_Success:
            default:
                return $this->t('mail_subject_debug');
        }
    }

    /**
     * Returns the subject for the mail.
     *
     * The subject depends on the result status.
     *
     * @param array $result
     *
     * @return array
     */
    protected function getStatusSpecificBody(array $result)
    {
        $texts = array();
        switch ($result['status']) {
            case PluginConfig::Status_Exception:
            case PluginConfig::Status_Errors:
                $texts['text'] = $this->t('mail_text_errors');
                $texts['html'] = $this->t('mail_html_errors');
                break;
            case PluginConfig::Status_Warnings:
                $texts['text'] = $this->t('mail_text_warnings');
                $texts['html'] = $this->t('mail_html_warnings');
                break;
            case PluginConfig::Status_Success:
            default:
                $texts['text'] = $this->t('mail_text_debug');
                $texts['html'] = $this->t('mail_html_debug');
                break;
        }
        return $texts;
    }

    /**
     * Returns the mail body as text and as html.
     *
     * @param array $result
     * @param string[] $messages
     * @param string $invoiceSourceType
     * @param string $invoiceSourceReference
     *
     * @return array
     *   An array with keys text and html.
     */
    protected function getBody(array $result, array $messages, $invoiceSourceType, $invoiceSourceReference)
    {
        $bodyTexts = $this->getStatusSpecificBody($result);
        $replacements = array(
            '{invoice_source_type}' => $this->t($invoiceSourceType),
            '{invoice_source_reference}' => $invoiceSourceReference,
            '{acumulus_invoice_id}' => isset($result['invoice']['invoicenumber']) ? $result['invoice']['invoicenumber'] : $this->t('message_no_invoice'),
            '{status}' => $result['status'],
            '{status_message}' => $this->service->getStatusText($result['status']),
            '{status_specific_text}' => $bodyTexts['text'],
            '{status_specific_html}' => $bodyTexts['html'],
            '{messages_text}' => $this->service->messagesToText($messages),
            '{messages_html}' => $this->service->messagesToHtml($messages),
        );
        $text = $this->t('mail_text');
        $text = strtr($text, $replacements);
        $html = $this->t('mail_html');
        $html = strtr($html, $replacements);
        return array('text' => $text, 'html' => $html);
    }
}
