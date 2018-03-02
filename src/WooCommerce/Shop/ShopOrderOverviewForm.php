<?php
namespace Siel\Acumulus\WooCommerce\Shop;

use Siel\Acumulus\Api;
use Siel\Acumulus\Config\ConfigInterface;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Invoice\Source;
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

    const DateFormat_Full = 'Y-m-d H:i:s';
    const DateFormat_Date = 'l j F Y';

    /** @var \Siel\Acumulus\Web\Service */
    protected $service;

    /** @var \Siel\Acumulus\Shop\AcumulusEntryManager */
    protected $acumulusEntryManager;

    /** @var \Siel\Acumulus\Shop\AcumulusEntry */
    protected $localEntryInfo;

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

        $translations = new ShopOrderOverviewFormTranslations();
        $this->translator->add($translations);

        $this->service = $service;
        $this->acumulusEntryManager = $acumulusEntryManager;
        $this->localEntryInfo = null;
        $this->source = null;
    }

    /**
     * @param \Siel\Acumulus\Invoice\Source $source
     */
    public function setSource(Source $source)
    {
        $this->source = $source;
        if ($this->source !== null) {
            $this->localEntryInfo = $this->acumulusEntryManager->getByInvoiceSource($this->source);
        }
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

        if ($this->localEntryInfo === null) {
            $status = static::Status_NotSent;
        }
        elseif ($this->localEntryInfo->getEntryId() === null) {
            $status = static::Status_SentConcept;
        }
        else {
            $result = $this->service->getEntry($this->localEntryInfo->getEntryId());
            $entry = $result->getResponse();
            if ($result->hasCodeTag('XGYBSN000')) {
                $status = static::Status_NonExisting;
            } elseif (empty($entry)) {
                $status = static::Status_CommunicationError;
            } elseif (!empty($entry['deleted'])) {
                $status = static::Status_Deleted;
            } else {
                $status = static::Status_Sent;
            }
        }

        $statusField = array(
            'type' => 'markup',
            'label' => $this->t('acumulus_invoice'),
            'value' => $this->t($status),
        );

        // 1st fieldset: Acumulus invoice info.
        $fields['acumulusInvoiceInfoHeader'] = array(
            'type' => 'fieldset',
            'fields' => array(
                $statusField,
            ),
        );

        switch ($status) {
            case static::Status_NotSent:
                $additionalFields = $this->getNotSentFields();
                break;
            case static::Status_SentConcept:
                $additionalFields = $this->getConceptFields($this->localEntryInfo);
                break;
            case static::Status_CommunicationError:
                /** @noinspection PhpUndefinedVariableInspection */
                $additionalFields = $this->getCommunicationErrorFields($this->localEntryInfo, $result);
                break;
            case static::Status_NonExisting:
                $additionalFields = $this->getNonExistingFields($this->localEntryInfo);
                break;
            case static::Status_Deleted:
                /** @noinspection PhpUndefinedVariableInspection */
                $additionalFields = $this->getDeletedFields($this->localEntryInfo, $entry);
                break;
            case static::Status_Sent:
                /** @noinspection PhpUndefinedVariableInspection */
                $additionalFields = $this->getEntryFields($this->localEntryInfo, $entry);
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
        $fields['acumulusInvoiceInfoHeader']['fields'] = array_merge($fields['acumulusInvoiceInfoHeader']['fields'], $additionalFields);

        return $fields;
//        return $fields['acumulusInvoiceInfoHeader']['fields'];
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
        // @todo. Buton "Send Now"
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent as
     * concept.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getConceptFields(BaseAcumulusEntry $localEntryInfo)
    {
        $fields = array();

        $fields += array(
            'date_sent' => $this->getDateSent($localEntryInfo),
            'info_concept' => array(
                'type' => 'markup',
                'value' => $this->t('info_concept'),
            ),
            // @todo: button "Send Again";
        );

        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * a communication error occurred in retrieving the entry.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     * @param \Siel\Acumulus\Web\Result $result
     *   The result that details the error.
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getCommunicationErrorFields(BaseAcumulusEntry $localEntryInfo, Result $result)
    {
        $fields = array();
        $fields += array(
            'date_sent' => $this->getDateSent($localEntryInfo),
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
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getNonExistingFields(BaseAcumulusEntry $localEntryInfo)
    {
        $fields = array();
        $fields += array(
            'date_sent' => $this->getDateSent($localEntryInfo),
            'info_non_existing' => array(
                'type' => 'markup',
                'value' => $this->t('info_concept'),
            ),
            // @todo: button "Send Again";
        );
        return $fields;
    }

    /**
     * Returns additional form fields to show when the invoice has been sent but
     * subsequently has been deleted in Acumulus.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     * @param array $entry
     *
     * @return array[]
     *   Array of form fields.
     */
    protected function getDeletedFields(BaseAcumulusEntry $localEntryInfo, array $entry)
    {
        $fields = array();
        $fields += array(
            'invoice_number' => $this->getInvoiceNumber($entry),
            'date_sent' => $this->getDateSent($localEntryInfo),
            'date_deleted' => array(
                'type' => 'markup',
                'label' => $this->t('date_sent'),
                'value' => date(static::DateFormat_Date, $entry['deleted']),
            ),
            'info_deleted' => array(
                'type' => 'markup',
                'value' => $this->t('info_deleted'),
            ),
            // @todo: button "Send Again";
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
         *   * deleted
         */

        $fields = array();
        $fields += array(
            'date_sent' => $this->getDateSent($localEntryInfo),
            'invoice_number' => $this->getInvoiceNumber($entry),
            'date_invoice' => array(
                'type' => 'markup',
                'label' => $this->t('date_invoice'),
                'value' => $entry['entrydate'],
            ),
            'vat_type' => $this->getVatType($entry),
        );
        $fields += $this->getPaymentStatus($entry);
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

    /**
     * Returns the date that the invoice was sent to Acumulus.
     *
     * @param \Siel\Acumulus\Shop\AcumulusEntry $localEntryInfo
     *
     * @return array
     *   Form field with the date that the invoice was sent to Acumulus.
     */
    protected function getDateSent(BaseAcumulusEntry $localEntryInfo)
    {
        return array(
            'type' => 'markup',
            'label' => $this->t('date_sent'),
            'value' => date(static::DateFormat_Date, $localEntryInfo->getUpdated()),
        );
    }

    protected function getVatType(array $entry)
    {
        if (!empty($entry['vatreversecharge'])) {
            if (!empty($entry['foreigneu'])) {
                $vatType = API::VatType_EuReversed;
            } elseif (!empty($entry['marginscheme'])) {
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
    protected function getPaymentStatus(array $entry)
    {
        $fields = array();
        $paymentStatus = isset($entry['paymentstatus']) ? (int) $entry['paymentstatus'] : 0;
        $fields['payment_status'] = array(
            'type' => 'markup',
            'label' => $this->t('payment_status'),
            'value' => $this->t($paymentStatus !== 0 ? ('payment_status_' . $paymentStatus) : 'unknown'),
        );
        if ($paymentStatus === API::PaymentStatus_Paid) {
            $fields['payment_date'] = array(
                'type' => 'markup',
                'label' => $this->t('date_payment'),
                'value' => !empty($entry['paymentdate']) ? $entry['paymentdate'] : $this->t('unknown'),
            );
        }
        return $fields;
    }
}
