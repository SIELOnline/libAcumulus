<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use DateTime;
use Siel\Acumulus\Api;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Config\ShopCapabilities;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\FormHelper;
use Siel\Acumulus\Helpers\Number;
use Siel\Acumulus\Helpers\Translator;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Web\Result;
use Siel\Acumulus\Web\Service;

/**
 * Class ShopOrderOverviewForm defines the shop order status oveview form.
 *
 * This form is mostly informative but may contain some buttons and a few fields
 * to update the invoice in Acumulus.
 *
 * SECURITY REMARKS
 * ----------------
 */
class ShopOrderOverviewForm extends Form
{
    // Constants representing the status of the Acumulus invoice for a given
    // shop order or refund.
    const Invoice_NotSent = 'invoice_not_sent';
    const Invoice_Sent = 'invoice_sent';
    const Invoice_SentConcept  = 'invoice_sent_concept';
    const Invoice_Deleted = 'invoice_deleted';
    const Invoice_NonExisting = 'invoice_non_existing';
    const Invoice_CommunicationError = 'invoice_communication_error';

    const Status_Unknown = 0;
    const Status_Success = 1;
    const Status_Info = 2;
    const Status_Warning = 3;
    const Status_Error = 4;

    /** @var \Siel\Acumulus\Web\Service */
    private $service;

    /** @var \Siel\Acumulus\Shop\AcumulusEntryManager */
    private $acumulusEntryManager;

    /** @var \Siel\Acumulus\Invoice\Source */
    private $source;

    /**
     * One of the Result::Status_... constants.
     *
     * @var int
     */
    private $status;

    /**
     * A message indicating why the status is not OK..
     *
     * @var string
     */
    private $statusMessage;

    /**
     * @param \Siel\Acumulus\WooCommerce\Shop\AcumulusEntryManager $acumulusEntryManager
     * @param \Siel\Acumulus\Web\Service $service
     * @param \Siel\Acumulus\Helpers\FormHelper $formHelper
     * @param \Siel\Acumulus\Config\ShopCapabilities $shopCapabilities
     * @param \Siel\Acumulus\Config\Config $config
     * @param \Siel\Acumulus\Helpers\Translator $translator
     */
    public function __construct(AcumulusEntryManager $acumulusEntryManager, Service $service, FormHelper $formHelper, ShopCapabilities $shopCapabilities, Config $config, Translator $translator)
    {
        parent::__construct($formHelper, $shopCapabilities, $config, $translator);

        $translations = new InvoiceTranslations();
        $this->translator->add($translations);

        $translations = new ShopOrderOverviewFormTranslations();
        $this->translator->add($translations);

        $this->acumulusEntryManager = $acumulusEntryManager;
        $this->service = $service;
        $this->source = null;
        $this->status = static::Status_Unknown;
    }

    /**
     * @param \Siel\Acumulus\Invoice\Source $source
     */
    public function setSource(Source $source)
    {
        $this->source = $source;
    }

    /**
     * Sets the status, but only if it is "worse" than the current status.
     *
     *
     * @param int $status
     *   The status to set.
     * @param string $message
     *   Optionally, a message indicating what is wrong may be given.
     */
    private function setStatus($status, $message = '')
    {
        if ($status > $this->status) {
            $this->status = $status;
            // Save the message belonging to this worse state.
            if (!empty($message)) {
                $this->statusMessage = $message;
            }
        }
    }

    /**
     * Returns a string to use as css class for the current status.
     *
     * @param int $status
     *
     * @return string
     */
    public function getStatusClass($status = null)
    {
        if ($status === null) {
            $status = $this->status;
        }
        switch ($status) {
            case static::Status_Success:
                $result = 'success';
                break;
            case static::Status_Info:
                $result = 'info';
                break;
            case static::Status_Warning:
                $result = 'warning';
                break;
            case static::Status_Error:
            default:
                $result = 'error';
                break;
        }
        return $result;
    }

    /**
     * Returns an icon character that represents the current status.
     *
     * @param int $status
     *
     * @return string
     *   An icon character that represents the status.
     */
    private function getStatusIcon($status = null)
    {
        if ($status === null) {
            $status = $this->status;
        }
        switch ($status) {
            case static::Status_Success:
                $result = json_decode('"\u2714"');
                break;
            case static::Status_Info:
            case static::Status_Warning:
                $result = '!';
                break;
            case static::Status_Error:
            default:
                $result = json_decode('"\u2716"');
                break;
        }
        return $result;
    }

    /**
     * Returns a set of label attributes for the current status.
     *
     * @param int $status
     *
     * @return array
     *   A set of attributes to add to the label.
     */
    private function getStatusLabelAttributes($status = null)
    {
        if ($status === null) {
            $status = $this->status;
        }
        $status = $this->getStatusClass($status);
        $attributes = array(
            'class' => array('notice', 'notice-' . $status),
            'wrapper' => array(
                'class' => array('notice', 'notice-' . $status),
            ),
        );
        if (!empty($this->statusMessage)) {
            $attributes['title'] = $this->statusMessage;
        }
        return $attributes;
    }

    /**
     * Returns a description of the amount status.
     *
     * @param int $status
     *
     * @return string
     *   A description of the amount status.
     */
    private function getAmountStatusTitle($status)
    {
        $result = '';
        if ($status > static::Status_Success) {
            $result = $this->t('amount_status_' . $status);
        }
        return $result;
    }

    /**
     * Executes the form action on valid form submission.
     *
     * Override to implement the actual form handling, like saving values.
     *
     * @return bool
     *   Success.
     */
    protected function execute()
    {
        // @todo.
        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function getFieldDefinitions()
    {
        $fields = array();

        // 1st fieldset: Order.
        $source = $this->source;
        $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);
        $idPrefix = 'order_' . $source->getId();
        $fields1Source = $this->addIdPrefix($this->getFields1Source($source, $localEntryInfo), $idPrefix . '_');
        $fields[$idPrefix] = array(
            'type' => 'fieldset',
            'fields' => $fields1Source,
        );

        // Other fieldsets: creditNotes.
        $creditNotes = $source->getCreditNotes();
        foreach($creditNotes as $creditNote) {
            $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($creditNote);
            $idPrefix = 'credit_note_' . $creditNote->getId();
            $fields1Source = $this->addIdPrefix($this->getFields1Source($source, $localEntryInfo), $idPrefix . '_');
            $fields[$idPrefix] = array(
                'type' => 'details',
                'summary' => ucfirst($this->t($creditNote->getType())) . ' ' . $creditNote->getReference(),
                'fields' => $fields1Source,
            );
        }
        return $fields;
    }


    /**
     * Returns the overview for 1 source.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntryInfo
     *
     * @return array[]
     *   The fields that describe the status for 1 source.
     */
    private function getFields1Source(Source $source, $localEntryInfo)
    {
        // Get invoice status field and other invoice status related info.
        $statusInfo = $this->getInvoiceStatusInfo($localEntryInfo);
        /** @var string $invoiceStatus */
        $invoiceStatus = $statusInfo['status'];
        /** @var string $statusText */
        $statusText = $statusInfo['text'];
        /** @var string $statusDescription */
        $statusDescription = $statusInfo['description'];
        /** @var Result|null $result */
        $result = $statusInfo['result'];
        /** @var array $entry */
        $entry = $statusInfo['entry'];

        // Create and add additional fields based on invoice status.
        switch ($invoiceStatus) {
            case static::Invoice_NotSent:
                $additionalFields = $this->getNotSentFields();
                break;
            case static::Invoice_SentConcept:
                $additionalFields = $this->getConceptFields();
                break;
            case static::Invoice_CommunicationError:
                /** @noinspection PhpUndefinedVariableInspection */
                $additionalFields = $this->getCommunicationErrorFields($result);
                break;
            case static::Invoice_NonExisting:
                $additionalFields = $this->getNonExistingFields();
                break;
            case static::Invoice_Deleted:
                $additionalFields = $this->getDeletedFields();
                break;
            case static::Invoice_Sent:
                $additionalFields = $this->getEntryFields($source, $localEntryInfo, $entry);
                break;
            default:
                $additionalFields = array(
                    'unknown' => array(
                        'type' => 'markup',
                        'value' => sprintf($this->t('invoice_status_unknown'), $invoiceStatus),
                    )
                );
                break;
        }

        // Create main status field after we have the other fields, so we can
        // use the results in rendering the overall status.
        $fields = array(
            'status' => array(
                'type' => 'markup',
                'label' => $this->getStatusIcon(),
                'attributes' => array(
                    'label' => $this->getStatusLabelAttributes(),
                ),
                'value' => $statusText,
                'description' => $statusDescription,
            ),
        ) + $additionalFields;
        return $fields;
    }

    /**
     * Returns status related information.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntryInfo
     *
     * @return array
     *   Keyed array with keys:
     *   - status (string): 1 of the ShopOrderOverviewForm::Status_ constants.
     *   - result (\Siel\Acumulus\Web\Result?): result of the getEntry API call.
     *   - entry (array|null): the <entry> part of the getEntry API call.
     *   - statusField (array): a form field array representing the status.
     */
    private function getInvoiceStatusInfo($localEntryInfo)
    {
        $result = null;
        $entry = null;
        $arg1 = null;
        $arg2 = null;
        $description = '';
        if ($localEntryInfo === null) {
            $invoiceStatus = static::Invoice_NotSent;
            $this->setStatus(static::Status_Info);
        } else {
            $arg1 = $this->getDate($localEntryInfo->getUpdated());
            if ($localEntryInfo->getEntryId() === null) {
                $invoiceStatus = static::Invoice_SentConcept;
                $description = 'concept_description';
                $this->setStatus(static::Status_Warning);
            } else {
                $result = $this->service->getEntry($localEntryInfo->getEntryId());
                $entry = $this->sanitizeEntry($result->getResponse());
                if ($result->hasCodeTag('XGYBSN000')) {
                    $invoiceStatus = static::Invoice_NonExisting;
                    $this->setStatus(static::Status_Error);
                } elseif (empty($entry)) {
                    $invoiceStatus = static::Invoice_CommunicationError;
                    $this->setStatus(static::Status_Error);
                } elseif (!empty($entry['deleted'])) {
                    $invoiceStatus = static::Invoice_Deleted;
                    $this->setStatus(static::Status_Warning);
                    $arg2 = $entry['deleted'];
                } else {
                    $invoiceStatus = static::Invoice_Sent;
                    $arg1 = $entry['invoicenumber'];
                    $arg2 = $entry['entrydate'];
                    $this->setStatus(static::Status_Success, $this->t('invoice_status_ok'));
                }
            }
        }

        return array(
            'status' => $invoiceStatus,
            'result' => $result,
            'entry' => $entry,
            'text' => sprintf($this->t($invoiceStatus), $arg1, $arg2),
            'description' => $this->t($description),
        );
    }

    /**
     * Returns additional form fields to show when the invoice has not yet been
     * sent.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getNotSentFields()
    {
        $fields = array();
        // @todo: action fields are disabled for now: next version.
        $fields += array(
//          'send' => array(
//            'type' => 'button',
//            'value' => $this->t('send_now'),
//          ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent as
     * concept.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getConceptFields()
    {
        $fields = array();
        // @todo: action fields are disabled for now: next version.
        $fields += array(
//            'send' => array(
//                'type' => 'button',
//                'value' => $this->t('send_again'),
//            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * a communication error occurred in retrieving the entry.
     *
     * @param \Siel\Acumulus\Web\Result $result
     *   The result that details the error.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getCommunicationErrorFields(Result $result)
    {
        $fields = array();
        $fields += array(
            'messages' => array(
                'type' => 'markup',
                'label' => $this->t('messages'),
                'value' => $result->getMessages(Result::Format_FormattedText),
            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * does no longer exist.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getNonExistingFields()
    {
        $fields = array();
        // @todo: action fields are disabled for now: next version.
        $fields += array(
//            'send' => array(
//                'type' => 'button',
//                'value' => $this->t('send_again'),
//            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * subsequently has been deleted in Acumulus.
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getDeletedFields()
    {
        $fields = array();
        // @todo: action fields are disabled for now: next version.
        $fields += array(
//            'undelete' => array(
//                'type' => 'button',
//                'value' => $this->t('undelete'),
//            ),
//            'send' => array(
//                'type' => 'button',
//                'value' => $this->t('send_again'),
//            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice is still there.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     * @param array $entry
     *
     * @return array[]
     *   Array of form fields.
     */
    private function getEntryFields(Source $source, BaseAcumulusEntry $localEntryInfo, array $entry)
    {
        /* keys in $entry array:
         *   - entryid
         *   * entrydate: yy-mm-dd
         *   - entrytype
         *   - entrydescription
         *   - entrynote
         *   - fiscaltype
         *   * vatreversecharge: 0 or 1
         *   * foreigneu: 0 or 1
         *   * foreignnoneu: 0 or 1
         *   * marginscheme: 0 or 1
         *   * foreignvat: 0 or 1
         *   - contactid
         *   - accountnumber
         *   - costcenterid
         *   - costtypeid
         *   * invoicenumber
         *   - invoicenote
         *   - descriptiontext
         *   - invoicelayoutid
         *   - totalvalueexclvat
         *   - totalvalue
         *   - paymenttermdays
         *   * paymentdate: yy-mm-dd
         *   * paymentstatus: 1 or 2
         *   * deleted: timestamp
         */
        $fields = $this->getVatTypeField($entry)
            + $this->getAmountFields($source, $entry)
            + $this->getPaymentStatusFields($source, $entry)
            + $this->getLinksField($localEntryInfo->getToken());

        return $fields;
    }

    /**
     * Returns the vat type field.
     *
     * @param array $entry
     *
     * @return array
     *    The vattype field.
     */
    private function getVatTypeField(array $entry)
    {
        if (!empty($entry['vatreversecharge'])) {
            if (!empty($entry['foreigneu'])) {
                $vatType = API::VatType_EuReversed;
            } else {
                $vatType = API::VatType_NationalReversed;
            }
        } elseif (!empty($entry['marginscheme'])) {
            $vatType = API::VatType_MarginScheme;
        } elseif (!empty($entry['foreignvat'])) {
            $vatType = API::VatType_ForeignVat;
        } elseif (!empty($entry['foreignnoneu'])) {
            $vatType = API::VatType_RestOfWorld;
        } else {
            $vatType = API::VatType_National;
        }
        return array(
            'vat_type' => array(
            'type' => 'markup',
            'label' => $this->t('vat_type'),
            'value' => $this->t('vat_type_' . $vatType),
            ),
        );
    }

    /**
     * Returns the payment status and date (if status is paid) of the invoice.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param array $entry
     *
     * @return array[]
     *   array with form fields with the payment status and date (if paid) of
     *   the invoice.
     */
    private function getPaymentStatusFields(Source $source, array $entry)
    {
        $fields = array();
        $paymentStatus = $entry['paymentstatus'];
        $paymentDate = $entry['paymentdate'];

        $paymentStatusText = $paymentStatus !== 0 ? ('payment_status_' . $paymentStatus) : 'unknown';
        if ($paymentStatus === API::PaymentStatus_Paid && !empty($paymentDate)) {
            $paymentStatusText .= '_date';
        }
        $paymentStatusText = sprintf($this->t($paymentStatusText), $paymentDate);

        $localPaymentStatus = $source->getPaymentStatus();
        if ($localPaymentStatus !== $paymentStatus) {
            $paymentCompareStatus = $paymentStatus === API::PaymentStatus_Paid ? static::Status_Warning : static::Status_Info;
            $paymentCompareStatustext = $this->t('payment_status_not_equal');
            $this->setStatus($paymentCompareStatus, $paymentCompareStatustext);
        } else {
            $paymentCompareStatus = static::Status_Success;
        }

        $paymentStatusMarkup = sprintf('<span class="notice-%s">%s</span>', $this->getStatusClass($paymentCompareStatus), $paymentStatusText);
        $fields['payment_status'] = array(
            'type' => 'markup',
            'label' => $this->t('payment_status'),
            'value' => $paymentStatusMarkup,
        );
        if (!empty($paymentCompareStatustext)) {
            $fields['payment_status'] = array_merge_recursive($fields['payment_status'],
                array(
                    'attributes' => array(
                        'title' => $paymentCompareStatustext,
                    ),
                )
            );
        }

        // @todo: action fields are disabled for now: next version.
//        if ($paymentStatus === API::PaymentStatus_Paid) {
//            $fields['set_paid'] = array(
//                'type' => 'button',
//                'value' => $this->t('set_due'),
//            );
//        } else {
//            $fields['payment_date'] = array(
//                'type' => 'date',
//                'label' => $this->t('payment_date'),
//                'default' => date(API::DateFormat_Iso),
//            );
//            $fields['set_paid'] = array(
//                'type' => 'button',
//                'value' => $this->t('set_paid'),
//            );
//        }

        return $fields;
    }

    /**
     * Returns the amounts of this invoice.
     *
     * @param \Siel\Acumulus\Invoice\Source $source
     * @param array $entry
     *
     * @return array[]
     *   Array with form fields with the payment status and date (if paid) of
     *   the invoice.
     */
    private function getAmountFields(Source $source, array $entry)
    {
        $fields = array();
        if (!empty($entry['totalvalue']) && !empty($entry['totalvalueexclvat'])) {
            // Get Acumulus amounts.
            $amountExAcumulus = $entry['totalvalueexclvat'];
            $amountIncAcumulus = $entry['totalvalue'];
            $amountVatAcumulus = $amountIncAcumulus - $amountExAcumulus;

            // Get local amounts.
            $localTotals = $source->getTotals();

            // Compare.
            $amountExStatus = $this->getAmountStatus($amountExAcumulus, $localTotals[Meta::InvoiceAmount]);
            $amountIncStatus = $this->getAmountStatus($amountIncAcumulus, $localTotals[Meta::InvoiceAmountInc]);
            $amountVatStatus = $this->getAmountStatus($amountVatAcumulus, $localTotals[Meta::InvoiceVatAmount]);

            $amountEx = $this->getFormattedAmount($amountExAcumulus, $amountExStatus);
            $amountInc = $this->getFormattedAmount($amountIncAcumulus, $amountIncStatus);
            $amountVat = $this->getFormattedAmount($amountVatAcumulus, $amountVatStatus);

            $fields['invoice_amount'] = array(
                'type' => 'markup',
                'label' => $this->t('invoice_amount'),
                'value' => sprintf('<div class="acumulus-amount">%1$s%2$s %4$s%3$s</div>', $amountEx, $amountVat, $amountInc, $this->t('vat')),
            );
        }
        return $fields;
    }

    /**
     * Returns the status of an amount by comparing it with its local value.
     *
     * If the amounts differ:
     * - < 0.5 cent, they are considered equal and 'success' will be returned.
     * - < 2 cents, it is considered a mere rounding error and 'info' will be returned.
     * - < 5 cents, it is considered a probable error and 'warning' will be returned.
     * - >= 5 cents, it is considered an error and 'error' will be returned.
     *
     * @param float $amount
     * @param float $amountLocal
     *
     * @return int
     *   One of the Status_... constants.
     */
    private function getAmountStatus($amount, $amountLocal)
    {
        if (Number::floatsAreEqual($amount, $amountLocal)) {
            $status = static::Status_Success;
        } elseif (Number::floatsAreEqual($amount, $amountLocal, 0.02)) {
            $status = static::Status_Info;
        } elseif (Number::floatsAreEqual($amount, $amountLocal, 0.05)) {
            $status = static::Status_Warning;
        } else {
            $status = static::Status_Error;
        }
        return $status;
    }

    /**
     * Formats an amount in html, adding classes given the status.
     *
     * @param float $amount
     * @param int $status
     *   One of the Status_... constants.
     *
     * @return string
     *   An html string representing the amount and its status.
     */
    private function getFormattedAmount($amount, $status)
    {
        $currency = '€';
        $sign = $amount < 0.0 ? '-' : '';
        $amount = abs($amount);
        $statusClass = $this->getStatusClass($status);
        $statusMessage = $this->getAmountStatusTitle($status);
        $this->setStatus($status, $statusMessage);
        if (!empty($statusMessage)) {
            $statusMessage = " title=\"$statusMessage\"";
        }

        $result = '';
        $result .= '<span class="sign">' . $sign . '</span>';
        $result .= '<span class="currency">' . $currency . '</span>';
        $result .= number_format($amount, 2, ',', '.');
        // Prevents warning "There should be a space between attribute ...
        $wrapperBegin = "<span class=\"amount notice-$statusClass\"" . $statusMessage . '>';
        $result = $wrapperBegin . $result . '</span>';
        return $result;
    }

    /**
     * Returns links to the invoice and packing slip documents.
     *
     * @param string $token
     *
     * @return array[]
     *   Array with form field that contains links to documents related to this
     *   invoice.
     */
    private function getLinksField($token)
    {
        $uri = $this->service->getInvoicePdfUri($token);
        $text = ucfirst($this->t('invoice'));
        $title = sprintf($this->t('open_as_pdf'), $text);
        /** @noinspection HtmlUnknownTarget */
        $invoiceLink = sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>', $uri, $text, $title, 'fa fa-file-pdf-o basic-icon fa-color-pdf pdf pdf-invoice');

        $uri = $this->service->getPackingSlipUri($token);
        $text = ucfirst($this->t('packing_slip'));
        $title = sprintf($this->t('open_as_pdf'), $text);
        /** @noinspection HtmlUnknownTarget */
        $packingSlipLink = sprintf('<a class="%3$s" href="%1$s" title="%3$s">%2$s</a>', $uri, $text, $title, 'fa fa-file-pdf-o basic-icon fa-color-pdf pdf pdf-packing-slip');

        $fields = array(
            'links' => array(
                'type' => 'markup',
                'label' => $this->t('documents'),
                'value' => "$invoiceLink $packingSlipLink",
            ),
        );
        return $fields;
    }

    /**
     * Returns a locale aware formatted date.
     *
     * @param int $timestamp
     *
     * @return string
     */
    private function getDate($timestamp)
    {
        return date(API::DateFormat_Iso, $timestamp);
    }

    /**
     * Adds a prefix to all keys in the set of $fields.
     *
     * This is done to ensure unique id's in case of repeating fieldsets.
     *
     * @param array[] $fields
     * @param string $idPrefix
     *
     * @return array[]
     *
     */
    private function addIdPrefix($fields, $idPrefix)
    {
        $result = array();
        foreach ($fields as $key => $field) {
            $newKey = $idPrefix . $key;
            $result[$newKey] = $field;
            if (isset($field['fields'])) {
                $result[$newKey]['fields'] = $this->addIdPrefix($field['fields'], $newKey . '_');
            }
        }
        return $result;
    }

    /**
     * Sanitizes an entry struct received via an getEntry API call.
     *
     * The info received from an external API call must not be trusted, so it
     * should be sanitized. As most info from this API call is placed in markup
     * fields we cannot rely on the FormRenderer or the webshop's form API
     * (which do not sanitize markup fields).
     *
     * So we sanitize the values in the struct itself before using them:
     * - Int, float, and bool fields are cast to their proper type.
     * - Date strings are parsed to a DateTime and formatted back to a date
     *   string.
     * - Strings that can only contain a restricted set of values are checked
     *   against that set and emptied if not part of it.
     * - Free string values are escaped to safe html.
     * - Keys we don't use are not returned. This keeps the output safe when a
     *   future API version returns additional fields and we forget to sanitize
     *   it and thus use it non sanitised.
     *
     * @param $entry
     *
     * @return mixed
     *   The sanitized entry struct.
     */
    private function sanitizeEntry($entry)
    {
        if (!empty($entry)) {
            /* @todo: keys in $entry array that are not yet used and not yet sanitized:
             *   - entrytype
             *   - entrydescription
             *   - entrynote
             *   - fiscaltype
             *   - contactid
             *   - accountnumber
             *   - costcenterid
             *   - costtypeid
             *   - invoicenote
             *   - descriptiontext
             *   - invoicelayoutid
             *   - token
             *   - paymenttermdays
             */
            $result['entryid'] = $this->sanitizeEntryIntValue($entry, 'entryid');
            $result['entrydate'] = $this->sanitizeEntryDateValue($entry, 'entrydate');
            $result['vatreversecharge'] = $this->sanitizeEntryBoolValue($entry, 'vatreversecharge');
            $result['foreigneu'] = $this->sanitizeEntryBoolValue($entry, 'foreigneu');
            $result['foreignnoneu'] = $this->sanitizeEntryBoolValue($entry, 'foreignnoneu');
            $result['marginscheme'] = $this->sanitizeEntryBoolValue($entry, 'marginscheme');
            $result['foreignvat'] = $this->sanitizeEntryBoolValue($entry, 'foreignvat');
            $result['invoicenumber'] = $this->sanitizeEntryIntValue($entry, 'invoicenumber');
            $result['totalvalueexclvat'] = $this->sanitizeEntryFloatValue($entry, 'totalvalueexclvat');
            $result['totalvalue'] = $this->sanitizeEntryFloatValue($entry, 'totalvalue');
            $result['paymentstatus'] = $this->sanitizeEntryIntValue($entry, 'paymentstatus');
            $result['paymentdate'] = $this->sanitizeEntryDateValue($entry, 'paymentdate');
            $result['deleted'] = $this->sanitizeEntryStringValue($entry, 'deleted');
        } else {
            $result = null;
        }
        return $result;
    }

    /**
     * Returns a html safe version of a string in an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return string
     *   The html safe version of the value under this key or the empty string
     *   if not set.
     */
    private function sanitizeEntryStringValue(array $entry, $key)
    {
        return !empty($entry[$key]) ? htmlspecialchars($entry[$key], ENT_NOQUOTES) : '';
    }

    /**
     * Returns a sanitized integer value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return int
     *   The int value of the value under this key.
     */
    private function sanitizeEntryIntValue(array $entry, $key)
    {
        return !empty($entry[$key]) ? (int) $entry[$key] : 0;
    }

    /**
     * Returns a sanitized float value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return int
     *   The float value of the value under this key.
     */
    private function sanitizeEntryFloatValue(array $entry, $key)
    {
        return !empty($entry[$key]) ? (float) $entry[$key] : 0.0;
    }

    /**
     * Returns a sanitized bool value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return bool
     *   The bool value of the value under this key. True values are represented
     *   by 1, false values by 0.
     */
    private function sanitizeEntryBoolValue(array $entry, $key)
    {
        return isset($entry[$key]) && $entry[$key] == 1;
    }

    /**
     * Returns a sanitized date value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return string
     *   The date value (yyyy-mm-dd) of the value under this key or the empty
     *   string, if the string is not in the valid date format (yyyy-mm-dd).
     */
    private function sanitizeEntryDateValue(array $entry, $key)
    {
        $date = '';
        if (!empty($entry[$key])) {
            $date = DateTime::createFromFormat(API::DateFormat_Iso, $entry[$key]);
            if ($date instanceof DateTime) {
                $date = $date->format(Api::DateFormat_Iso);
            } else {
                $date = '';
            }
        }
        return $date;
    }
}
