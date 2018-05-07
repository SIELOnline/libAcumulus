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
    const Status_NotSent = 'status_not_sent';
    const Status_Sent = 'status_sent';
    const Status_SentConcept  = 'status_sent_concept';
    const Status_Deleted = 'status_deleted';
    const Status_NonExisting = 'status_non_existing';
    const Status_CommunicationError = 'status_communication_error';

    /** @var \Siel\Acumulus\Web\Service */
    protected $service;

    /** @var \Siel\Acumulus\Shop\AcumulusEntryManager */
    protected $acumulusEntryManager;

    /** @var \Siel\Acumulus\Invoice\Source */
    protected $source;

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
    }

    /**
     * @param \Siel\Acumulus\Invoice\Source $source
     */
    public function setSource(Source $source)
    {
        $this->source = $source;
    }

    /**
     * @inheritDoc
     */
    protected function getPostedValues()
    {
        $result = parent::getPostedValues();
        // WordPress calls wp_magic_quotes() on every request to add magic
        // quotes to form input: we undo this here.
        $result = stripslashes_deep($result);
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
        $fields1Source = $this->addIdPrefix($this->getFields1Source($localEntryInfo), $idPrefix . '_');
        $fields[$idPrefix] = array(
            'type' => 'fieldset',
            'fields' => $fields1Source,
        );

        // Other fieldsets: creditNotes.
        $creditNotes = $this->source->getCreditNotes();
        foreach($creditNotes as $creditNote) {
            $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($creditNote);
            $idPrefix = 'credit_note_' . $creditNote->getId();
            $fields1Source = $this->addIdPrefix($this->getFields1Source($localEntryInfo), $idPrefix . '_');
            $fields[$idPrefix] = array(
                'type' => 'fieldset',
                'legend' => $this->t($creditNote->getType()) . ' ' . $creditNote->getReference(),
                'fields' => $fields1Source,
            );
        }
        return $fields;
    }


    /**
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntryInfo
     *
     * @return array[]
     */
    protected function getFields1Source($localEntryInfo)
    {
        $statusInfo = $this->getStatus($localEntryInfo);
        /** @var string $status */
        $status = $statusInfo['status'];
        /** @var array $statusField */
        $statusField = $statusInfo['field'];
        /** @var Result|null $result */
        $result = $statusInfo['result'];
        /** @var array $entry */
        $entry = $statusInfo['entry'];

        $fields = array(
            'status' => $statusField,
        );

        switch ($status) {
            case static::Status_NotSent:
                $additionalFields = $this->getNotSentFields();
                break;
            case static::Status_SentConcept:
                $additionalFields = $this->getConceptFields();
                break;
            case static::Status_CommunicationError:
                /** @noinspection PhpUndefinedVariableInspection */
                $additionalFields = $this->getCommunicationErrorFields($result);
                break;
            case static::Status_NonExisting:
                $additionalFields = $this->getNonExistingFields();
                break;
            case static::Status_Deleted:
                $additionalFields = $this->getDeletedFields();
                break;
            case static::Status_Sent:
                $additionalFields = $this->getEntryFields($localEntryInfo, $entry);
                break;
            default:
                $additionalFields = array(
                    'unknown' => array(
                        'type' => 'markup',
                        'value' => sprintf($this->t('status_unknown'), $status),
                    )
                );
                break;
        }

        $fields = array_merge($fields, $additionalFields);
        return $fields;
    }

    /**
     * Returns status related information
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry|null $localEntryInfo
     *
     * @return array
     *   Keyed array with keys:
     *   - status (string): 1 of the ShopOrderOverviewForm::Status_ constants.
     *   - result (\Siel\Acumulus\Web\Result?): Result of the getEntry API call.
     *   - entry (array|null): the <entry> part of the getEntryAPI call.
     *   - statusField (array): A form field array representing the status.
     */
    protected function getStatus($localEntryInfo)
    {
        $result = null;
        $entry = null;
        $arg1 = null;
        $arg2 = null;
        $description = null;
        if ($localEntryInfo === null) {
            $status = static::Status_NotSent;
            $statusIndicator = 'info';
        }
        else {
            $arg1 = $this->getDate($localEntryInfo->getUpdated());
            if ($localEntryInfo->getEntryId() === null) {
                $status = static::Status_SentConcept;
                $description = 'concept_description';
                $statusIndicator = 'warning';
            }
            else {
                $result = $this->service->getEntry($localEntryInfo->getEntryId());
                $entry = $this->sanitizeEntry($result->getResponse());
                if ($result->hasCodeTag('XGYBSN000')) {
                    $status = static::Status_NonExisting;
                    $statusIndicator = 'error';
                } elseif (empty($entry)) {
                    $status = static::Status_CommunicationError;
                    $statusIndicator = 'error';
                } elseif (!empty($entry['deleted'])) {
                    $status = static::Status_Deleted;
                    $statusIndicator = 'warning';
                    $arg2 = $entry['deleted'];
                } else {
                    $status = static::Status_Sent;
                    $arg1 = $entry['invoicenumber'];
                    $arg2 = $entry['entrydate'];
                    $statusIndicator = 'success';
                }
            }
        }

        $statusField = array(
            'type' => 'markup',
            'label' => $this->getStatusIcon($statusIndicator),
            'value' => sprintf($this->t($status), $arg1, $arg2),
            'attributes' => array(
                'label' => $this->getLabelAttributes($statusIndicator),
            ),
        );
        if ($description !== null) {
            $statusField['description'] = $this->t($description);
        }

        return array(
            'status' => $status,
            'result' => $result,
            'entry' => $entry,
            'field' => $statusField,
        );
    }

    /**
     * Returns additional form fields to show when the Acumulus invoice has
     * not yet been sent.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getNotSentFields()
    {
        $fields = array();
        $fields += array(
          'send' => array(
            'type' => 'button',
            'value' => $this->t('send_now'),
          ),
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
    protected function getConceptFields()
    {
        $fields = array();
        $fields += array(
            'send' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
            ),
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
    protected function getCommunicationErrorFields(Result $result)
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
    protected function getNonExistingFields()
    {
        $fields = array();
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * subsequently has been deleted in Acumulus.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getDeletedFields()
    {
        $fields = array();
        $fields += array(
            'send' => array(
                'type' => 'button',
                'value' => $this->t('send_again'),
            ),
            'undelete' => array(
                'type' => 'button',
                'value' => $this->t('undelete'),
            ),
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the Acumulus invoice is still there.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     * @param array $entry
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getEntryFields(BaseAcumulusEntry $localEntryInfo, array $entry)
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
        $fields = array();
        $fields += array(
            'invoice_number' => $this->getInvoiceNumber($entry),
            'invoice_date' => array(
                'type' => 'markup',
                'label' => $this->t('invoice_date'),
                'value' => $entry['entrydate'],
            ),
            'vat_type' => $this->getVatType($entry),
        );
        $fields += $this->getAmountFields($entry);
        $fields += $this->getPaymentStatusFields($entry);
        $fields += $this->getLinksField($localEntryInfo->getToken());

        $fields = array(
            'invoice_info' => array(
                'type' => 'fieldset',
                'legend' => '',
                'fields' => $fields,
            ),
        );

        return $fields;
    }

    /**
     * Returns the number of the invoice in Acumulus.
     *
     * @param array $entry
     *
     * @return array
     *   Form field with the number of the invoice in Acumulus.
     */
    protected function getInvoiceNumber(array $entry)
    {
        return array(
            'type' => 'markup',
            'label' => $this->t('invoice_number'),
            'value' => isset($entry['invoicenumber']) ? $entry['invoicenumber'] : $this->t('unknown'),
        );
    }

    protected function getVatType(array $entry)
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
            'type' => 'markup',
            'label' => $this->t('vat_type'),
            'value' => $this->t('vat_type_' . $vatType),
        );
    }

    /**
     * Returns the payment status and date (if status is paid) of the invoice.
     *
     * @param array $entry
     *
     * @return array[]
     *   array with form fields with the payment status and date (if paid) of
     *   the invoice.
     */
    protected function getPaymentStatusFields(array $entry)
    {
        $fields = array();
        $remotePaymentStatus = isset($entry['paymentstatus']) ? (int) $entry['paymentstatus'] : 0;
        $paymentDate = isset($entry['paymentdate']) ? $entry['paymentdate'] : '';
        $paymentStatusText = $remotePaymentStatus !== 0 ? ('payment_status_' . $remotePaymentStatus) : 'unknown';
        if ($remotePaymentStatus === API::PaymentStatus_Paid && !empty($paymentDate)) {
            $paymentStatusText .= '_date';
            $statusIndicator = $this->source->getPaymentState() === $remotePaymentStatus ? 'success' : 'warning';
        } else {
            $statusIndicator = $this->source->getPaymentState() === $remotePaymentStatus ? 'success' : 'info';
        }
        $fields['payment_status'] = array(
            'type' => 'markup',
            'label' => $this->t('payment_status'),
            'attributes' => array(
                'label' => $this->getLabelAttributes($statusIndicator),
            ),
            'value' => sprintf($this->t($paymentStatusText), $paymentDate),
        );
        if ($remotePaymentStatus === API::PaymentStatus_Paid) {
            $fields['set_paid'] = array(
                'type' => 'button',
                'value' => $this->t('set_due'),
            );
        } else {
            $fields['payment_date'] = array(
                'type' => 'date',
                'label' => $this->t('payment_date'),
                'default' => date(API::DateFormat_Iso),
            );
            $fields['set_paid'] = array(
                'type' => 'button',
                'value' => $this->t('set_paid'),
            );
        }
        return $fields;
    }

    /**
     * Returns the amounts of this invoice.
     *
     * @param array $entry
     *
     * @return array[]
     *   Array with form fields with the payment status and date (if paid) of
     *   the invoice.
     */
    protected function getAmountFields(array $entry)
    {
        $fields = array();
        if (!empty($entry['totalvalue']) && !empty($entry['totalvalueexclvat'])) {
            $amountEx = $entry['totalvalueexclvat'];
            $amountInc = $entry['totalvalue'];
            $amountVat = $amountInc - $amountEx;

            // Compare local amounts
            $amountIncLocal = $this->source->getSource()->get_total();
            $amountVatLocal = $this->source->getSource()->get_total_tax();
            $amountExLocal = $amountIncLocal - $amountVatLocal;
            if (Number::floatsAreEqual($amountInc, $amountIncLocal) && Number::floatsAreEqual($amountEx, $amountExLocal)) {
                $statusIndicator = 'success';
            } elseif (Number::floatsAreEqual($amountInc, $amountIncLocal, 0.02) && Number::floatsAreEqual($amountEx, $amountExLocal, 0.02)) {
                $statusIndicator = 'info';
            } elseif (Number::floatsAreEqual($amountInc, $amountIncLocal, 0.05) && Number::floatsAreEqual($amountEx, $amountExLocal, 0.05)) {
                $statusIndicator = 'warning';
            } else {
                $statusIndicator = 'error';
            }

            $amountEx = wc_price($amountEx, array('currency' => 'EUR'));
            $amountInc = wc_price($amountInc, array('currency' => 'EUR'));
            if ($amountVat >= 0.0) {
                $amountVat = wc_price($amountVat, array('currency' => 'EUR'));
                $sign = '+';
            } else {
                $amountVat = wc_price(-$amountVat, array('currency' => 'EUR'));
                $sign = '-';
            }
            $fields['invoice_amount'] = array(
                'type' => 'markup',
                'label' => $this->getStatusIcon($statusIndicator) . ' ' . $this->t('invoice_amount'),
                'attributes' => array(
                    'label' => $this->getLabelAttributes($statusIndicator),
                ),
                'value' => sprintf('%1$s %2$s %3$s %4$s = %5$s', $amountEx, $sign, $amountVat, $this->t('vat'), $amountInc),
            );
        }
        return $fields;
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
    protected function getLinksField($token)
    {
        $invoiceUri = $this->service->getInvoicePdfUri($token);
        $invoiceText = $this->t('invoice');
        $invoicPdf = sprintf($this->t('open_as_pdf'), $invoiceText);
        /** @noinspection HtmlUnknownTarget */
        $invoiceLink = sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>', $invoiceUri, $invoiceText, $invoicPdf, 'fa fa-file-pdf-o basic-icon fa-color-pdf pdf pdf-invoice');
        $packingSlipUri = $this->service->getPackingSlipUri($token);
        $packingSlipText = $this->t('packing_slip');
        $packingSlipPdf = sprintf($this->t('open_as_pdf'), $packingSlipText);
        /** @noinspection HtmlUnknownTarget */
        $packingSlipLink = sprintf('<a class="%3$s" href="%1$s" title="%3$s">%2$s</a>', $packingSlipUri, $packingSlipText, $packingSlipPdf, 'fa fa-file-pdf-o basic-icon fa-color-pdf pdf pdf-packing-slip');
        $fields = array();
        $fields['links'] = array(
            'type' => 'markup',
            'label' => $this->t('documents'),
            'value' => "$invoiceLink $packingSlipLink",
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
    protected function getDate($timestamp)
    {
        return date(API::DateFormat_Iso, $timestamp);
    }

    /**
     * Returns the fieldset for 1 refund.
     *
     * @param \WC_Order_Refund $refund
     *
     * @return array[]
     */
    protected function getRefundFieldset($refund)
    {
        $id = (string) $refund->get_id();
        $fieldset = array(
            'type' => 'fieldset',
            'legend' => $this->t(Source::CreditNote) . " $id",
            'fields' => array()
        );

        return array('refund_' . $id => $fieldset);
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
    protected function addIdPrefix($fields, $idPrefix)
    {
        $result = array();
        foreach ($fields as $key => $field) {
            $newKey = $idPrefix . $key;
            $result[$newKey] = $field;
            if (isset($field['fields'])) {
                $result[$newKey]['fields'] = $this->addIdPrefix($field['fields'], $newKey);
            }
        }
        return $result;
    }

    /**
     * Returns an icon character that represents the status.
     *
     * @param $status
     *   Status indication, like success, info, warning, or error.
     *
     * @return string
     *   An icon character that represents the status.
     */
    protected function getStatusIcon($status)
    {
        switch ($status) {
            case 'success':
                $result = json_decode('"\u2714"');
                break;
            case 'info':
            case 'warning':
                $result = '!';
                break;
            case 'error':
            default:
                $result = json_decode('"\u2716"');
                break;
        }
        return $result;
    }

    /**
     * Returns a set of label attributes given a status indicator.
     *
     * @param $status
     *   Status indication, like success, info, warning, or error.
     *
     * @return array
     *   A set of attributes to add to the label.
     */
    protected function getLabelAttributes($status)
    {
        return array(
            'class' => array($status, 'notice', 'notice-' . $status),
            'wrapper' => array(
                'class' => array($status, 'notice', 'notice-' . $status),
            ),
        );
    }

    protected function sanitizeEntry($entry)
    {
        if (!empty($entry)) {
            /* keys in $entry array that are not yet used and not yet sanitized:
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
    protected function sanitizeEntryStringValue(array $entry, $key)
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
    protected function sanitizeEntryIntValue(array $entry, $key)
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
    protected function sanitizeEntryFloatValue(array $entry, $key)
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
    protected function sanitizeEntryBoolValue(array $entry, $key)
    {
        return isset($entry[$key]) && $entry[$key] == 1;
    }

    /**
     * Returns a sanitized date value of an entry record.
     *
     * @param array $entry
     * @param string $key
     *
     * @return DateTime|null
     *   The date value of the value under this key or null if the string
     *   is not in the valid date format (yyyy-mm-dd)
     */
    protected function sanitizeEntryDateValue(array $entry, $key)
    {
        $date = null;
        if (!empty($entry[$key])) {
            $date = DateTime::createFromFormat(API::DateFormat_Iso, $entry[$key]);
        }
        return $date instanceof DateTime ? $date : null;
    }
}
