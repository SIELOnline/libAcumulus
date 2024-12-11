<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

use Siel\Acumulus\Helpers\Severity;
use Siel\Acumulus\Mail\Mail;

/**
 * InvoiceAddMail creates and send the mail following an invoice add request.
 *
 * @method InvoiceAddResult getResult();
 * @method Source getSource();
 */
class InvoiceAddMail extends Mail
{
    /**
     * {@inheritdoc}
     *
     * In addition to the base implementation, this override also looks at:
     * - whether the invoice was sent as concept.
     * - the emailAsPdf setting.
     */
    protected function getSubjectBase(): string
    {
        $subject = parent::getSubjectBase();
        $result = $this->getResult();
        if ($result !== null) {
            $isConcept = $result->isConcept();
            if ($isConcept && !$result->isTestMode()) {
                $subject = $this->t('mail_subject_concept');
            }
        }
        return $subject;
    }

    protected function getSubjectResult(): string
    {
        $subjectResultPhrase = parent::getSubjectResult();
        $emailAsPdfSettings = $this->getConfig()->getEmailAsPdfSettings();
        if ($emailAsPdfSettings['emailAsPdf']) {
            $result = $this->getResult();
            if ($result !== null) {
                $isConcept = $result->isConcept();
                if ($result->isTestMode() || $isConcept || $result->hasError()) {
                    $emailAsPdfSettings = $this->getConfig()->getEmailAsPdfSettings();
                    if ($emailAsPdfSettings['emailAsPdf']) {
                        // Normally, Acumulus will send a pdf to the client, but due to
                        // 1 of the conditions above this was not done.
                        $subjectResultPhrase .= ', ' . $this->t('mail_subject_no_pdf');
                    }
                }
            }
        }
        return $subjectResultPhrase;
    }

    protected
    function getPlaceholders(): array
    {
        $acumulusInvoiceLabel = $this->t('document_invoice');
        $acumulusInvoiceId = $this->t('message_not_created');
        $invoiceInfo = $this->getResult()?->getMainApiResponse();
        if ($invoiceInfo !== null) {
            if (isset($invoiceInfo['invoicenumber'])) {
                $acumulusInvoiceId = $invoiceInfo['invoicenumber'];
            } elseif (isset($invoiceInfo['conceptid'])) {
                $acumulusInvoiceLabel = $this->t('document_concept_invoice');
                $acumulusInvoiceId = $invoiceInfo['conceptid'];
            }
        }
        return [
                '{acumulus_invoice_label}' => $acumulusInvoiceLabel,
                '{acumulus_invoice_id}' => $acumulusInvoiceId,
            ] + parent::getPlaceholders();
    }

    protected function getAboutLines(): array
    {
        return [
                '({shop}) {source_label}' => '{source_reference}',
                '{module_name} {acumulus_invoice_label}' => '{acumulus_invoice_id}'
            ] + parent::getAboutLines();
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
     * @return string[]
     *   An array with the status specific part of the body text in 2 formats,
     *   keyed by 'text' resp. 'html'.
     */
    protected function getIntroSentences(): array
    {
        $invoiceInfo = $this->getResult()->getMainApiResponse();
        $isConcept = $invoiceInfo !== null && !empty($invoiceInfo['conceptid']);
        $emailAsPdfSettings = $this->getConfig()->getEmailAsPdfSettings();
        $isEmailAsPdf = $emailAsPdfSettings['emailAsPdf'];

        // Collect the messages.
        $sentences = parent::getIntroSentences();
        switch ($this->getResult()->getSeverity()) {
            case Severity::Exception:
                break;
            case Severity::Error:
                if ($isEmailAsPdf) {
                    $sentences[] = 'mail_body_pdf_enabled';
                    $sentences[] = 'mail_body_pdf_not_sent_errors';
                }
                break;
            case Severity::Warning:
                if ($isConcept) {
                    array_pop($sentences);
                    $sentences[] = 'mail_body_concept';
                    if ($isEmailAsPdf) {
                        $sentences[] = 'mail_body_pdf_enabled';
                        $sentences[] = 'mail_body_pdf_not_sent_concept';
                    }
                }
                break;
            case Severity::Success:
            default:
                if ($isConcept) {
                    if ($this->getResult()->isTestMode()) {
                        array_pop($sentences);
                    }
                    $sentences[] = 'mail_body_concept';
                    if ($isEmailAsPdf) {
                        $sentences[] = 'mail_body_pdf_enabled';
                        $sentences[] = 'mail_body_pdf_not_sent_concept';
                    }
                }
                break;
        }

        return $sentences;
    }

}
