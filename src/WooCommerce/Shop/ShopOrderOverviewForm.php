<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;
use Siel\Acumulus\Shop\AcumulusEntry as BaseAcumulusEntry;
use Siel\Acumulus\Web\Result;
use Siel\Acumulus\Web\Service;

/**
 * Class ShopOrderOverviewForm defines the shop order status oveview form.
 *
 * This form is mostly informative but may contain some buttons and a few fields
 * to update the invocie in Acumulus.
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

    const DateFormat_Date = 'Y-m-d';

    /** @var \Siel\Acumulus\Web\Service */
    protected $service;

    /** @var \Siel\Acumulus\Shop\AcumulusEntryManager */
    protected $acumulusEntryManager;

    /** @var \Siel\Acumulus\Invoice\Source */
    protected $source;

  /**
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   * @param \Siel\Acumulus\Config\ConfigInterface $config
   * @param \Siel\Acumulus\Web\Service $service
   * @param \Siel\Acumulus\WooCommerce\Shop\AcumulusEntryManager $acumulusEntryManager
   */
    public function __construct(TranslatorInterface $translator, ConfigInterface $config, Service $service, AcumulusEntryManager $acumulusEntryManager)
    {
        parent::__construct($translator, $config);

        $translations = new InvoiceTranslations();
        $this->translator->add($translations);

        $translations = new ShopOrderOverviewFormTranslations();
        $this->translator->add($translations);

        $this->service = $service;
        $this->acumulusEntryManager = $acumulusEntryManager;
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
        $source = $this->source;
        $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($source);

        $fields = array();

        // 1st fieldset: Order.
        $fields['order_' . $source->getId()] = array(
            'type' => 'fieldset',
            'fields' => $this->getFields1Source($localEntryInfo),
        );

        // Get creditNotes fieldsets.
        $creditNotes = $this->source->getCreditNotes();
        foreach($creditNotes as $creditNote) {
            $localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($creditNote);
            $fields1Source = $this->getFields1Source($localEntryInfo);
            $fields['credit_note_' . $creditNote->getId()] = array(
                'type' => 'fieldset',
                'legend' => $this->t($creditNote->getType()) . ' ' . $creditNote->getReference(),
                'fields' => $fields1Source,
            );
        }
        return $fields;
    }


    /**
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     *
     * @return array[]
     */
    protected function getFields1Source(BaseAcumulusEntry $localEntryInfo)
    {
        $statusInfo = $this->getStatus($localEntryInfo);
        $status = $statusInfo['status'];
        $statusField = $statusInfo['field'];
        $result = $statusInfo['result'];
        $entry = $statusInfo['entry'];

        $fields = array(
            $statusField,
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
                /** @noinspection PhpUndefinedVariableInspection */
                $additionalFields = $this->getDeletedFields();
                break;
            case static::Status_Sent:
                /** @noinspection PhpUndefinedVariableInspection */
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
     * Returns additional form fields to show when the Acumulus invoice has
     * not yet been sent.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     *
     * @return array
     *   Array with status, result and entry.
     */
    protected function getStatus(BaseAcumulusEntry $localEntryInfo)
    {
        $arg1 = null;
        $arg2 = null;
        $description = null;
        if ($localEntryInfo === null) {
            $status = static::Status_NotSent;
        }
        else {
            $arg1 = $this->getDate($localEntryInfo->getUpdated());
            if ($localEntryInfo->getEntryId() === null) {
                $status = static::Status_SentConcept;
                $description = 'concept_description';
            }
            else {
                $result = $this->service->getEntry($localEntryInfo->getEntryId());
                $entry = $result->getResponse();
                if ($result->hasCodeTag('XGYBSN000')) {
                    $status = static::Status_NonExisting;
                } elseif (empty($entry)) {
                    $status = static::Status_CommunicationError;
                } elseif (!empty($entry['deleted'])) {
                    $status = static::Status_Deleted;
                    $arg2 = $entry['deleted'];
                } else {
                    $status = static::Status_Sent;
                }
            }
        }

        $statusField = array(
            'type' => 'markup',
//            'label' => $this->t('acumulus_invoice'),
            'value' => sprintf($this->t($status), $arg1, $arg2)
        );
        if ($description !== null) {
            $statusField['description'] = $this->t($description);
        }

        return array(
            'status' => $status,
            'result' => isset($result) ? $result: null,
            'entry' => isset($entry) ? $entry : null,
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
        $paymentStatus = isset($entry['paymentstatus']) ? (int) $entry['paymentstatus'] : 0;
        $paymentDate = isset($entry['paymentdate']) ? $entry['paymentdate'] : '';
        $paymentStatusText = $paymentStatus !== 0 ? ('payment_status_' . $paymentStatus) : 'unknown';
        if ($paymentStatus === API::PaymentStatus_Paid && !empty($paymentDate)) {
            $paymentStatusText .= '_date';
        }
        $fields['payment_status'] = array(
            'type' => 'markup',
            'label' => $this->t('payment_status'),
            'value' => sprintf($this->t($paymentStatusText), $paymentDate),
        );
        // @todo: compare with local paid state.
        if ($paymentStatus === API::PaymentStatus_Paid) {
            $fields['set_paid'] = array(
                'type' => 'button',
                'value' => $this->t('set_due'),
            );
        } else {
            $fields['payment_date'] = array(
                'type' => 'date',
                'label' => $this->t('payment_date'),
                'default' => date('Y-m-d'),
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
            // @todo: compare with local amounts.
            $amountEx = $entry['totalvalueexclvat'];
            $amountInc = $entry['totalvalue'];
            $amountVat = $amountInc - $amountEx;
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
                'label' => $this->t('invoice_amount'),
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
        /** @noinspection HtmlUnknownTarget */
        $invoiceLink = sprintf('<a class="%4$s" href="%1$s" title="%3$s">%2$s</a>', $invoiceUri, $this->t('invoice'), $this->t('open_as_pdf'), 'fa fa-file-pdf-o basic-icon fa-color-pdf pdf pdf-invoice');
        $packingSlipUri = $this->service->getPackingSlipUri($token);
        /** @noinspection HtmlUnknownTarget */
        $packingSlipLink = sprintf('<a class="%3$s" href="%1$s" title="%3$s">%2$s</a>', $packingSlipUri, $this->t('packing_slip'), $this->t('open_as_pdf'), 'fa fa-file-pdf-o basic-icon fa-color-pdf pdf pdf-packing-slip');
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
//        $currentLocale = setlocale(LC_TIME, '0');
//        $isWindows = strncasecmp(PHP_OS, 'WIN', 3) === 0;
//        setlocale(LC_TIME, $this->t($isWindows ? 'nld' : 'nl_NL'));
//        $result = strftime(static::DateFormat_Date, $timestamp);
        $result = date(static::DateFormat_Date, $timestamp);
//        setlocale(LC_TIME, $currentLocale);
        return $result;
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
}
