<?php
namespace Siel\Acumulus\Shop;

use DateTime;
use Siel\Acumulus\Helpers\Form;
use Siel\Acumulus\Helpers\TranslatorInterface;
use Siel\Acumulus\Invoice\Translations as InvoiceTranslations;

/**
 * Provides batch form handling.
 */
abstract class BatchForm extends Form {

  /** @var \Siel\Acumulus\Shop\InvoiceManager */
  protected $invoiceManager;

  /** @var array */
  protected $log;

  /**
   * @param Config $config
   * @param \Siel\Acumulus\Helpers\TranslatorInterface $translator
   * @param \Siel\Acumulus\Shop\InvoiceManager $invoiceManager
   */
  public function __construct(Config $config, TranslatorInterface $translator, InvoiceManager $invoiceManager) {
    parent::__construct($translator);

    $translations = new InvoiceTranslations();
    $this->translator->add($translations);

    $translations = new BatchFormTranslations();
    $this->translator->add($translations);

    $this->log = array();
    $this->invoiceManager = $invoiceManager;
  }

  protected function getDefaultFormValues() {
    $result = parent::getDefaultFormValues();
    if (!empty($this->log)) {
      $result['log'] = implode("\n", $this->log);
    }
    return $result;
  }

  protected function validate() {
    $invoiceSourceTypes = $this->invoiceManager->getSupportedInvoiceSourceTypes();
    if (empty($this->submittedValues['invoice_source_type'])) {
      $this->errorMessages['invoice_source_type'] = $this->t('message_validate_batch_source_type_required');
    }
    else if (!in_array($this->submittedValues['invoice_source_type'], $invoiceSourceTypes)) {
      $this->errorMessages['invoice_source_type'] = $this->t('message_validate_batch_source_type_invalid');
    }

    if (empty($this->submittedValues['invoice_source_reference_from']) && empty($this->submittedValues['date_from'])) {
      // Either a range of order id's or a range of dates should be entered.
      $this->errorMessages['invoice_source_reference_from'] = $this->t(count($invoiceSourceTypes) === 1 ? 'message_validate_batch_reference_or_date_1' : 'message_validate_batch_reference_or_date_2');
    }
    else if (!empty($this->submittedValues['invoice_source_reference_from']) && !empty($this->submittedValues['date_from'])) {
      // Not both ranges should be entered.
       $this->errorMessages['date_from'] = $this->t(count($invoiceSourceTypes) === 1 ? 'message_validate_batch_reference_and_date_1' : 'message_validate_batch_reference_and_date_2');
    }
    else if (!empty($this->submittedValues['invoice_source_reference_from'])) {
      // Date from is empty, we go for a range of order ids.
      // (We ignore any date to value.)
      // Single id or range of ids?
      if (!empty($this->submittedValues['invoice_source_reference_to']) && $this->submittedValues['invoice_source_reference_to'] < $this->submittedValues['invoice_source_reference_from']) {
        // order id to is smaller than order id from.
         $this->errorMessages['invoice_source_reference_to'] = $this->t('message_validate_batch_bad_order_range');
      }
    }
    else /*if (!empty($this->submittedValues['date_to'])) */ {
      // Range of dates has been filled in.
      // We ignore any order # to value.
      $checkRange = true;
      $dateFormat = $this->getDateFormat();
      if (!DateTime::createFromFormat($dateFormat, $this->submittedValues['date_from'])) {
        // Date from not a valid date.
        $this->errorMessages['date_from'] = sprintf($this->t('message_validate_batch_bad_date_from'), $this->getShopDateFormat());
        $checkRange = false;
      }
      if ($this->submittedValues['date_to'] !== $this->submittedValues['date_from'] && !DateTime::createFromFormat($dateFormat, $this->submittedValues['date_to'])) {
        // Date to not a valid date.
        $this->errorMessages['date_to'] = sprintf($this->t('message_validate_batch_bad_date_to'), $this->getShopDateFormat());
        $checkRange = false;
      }
      if ($checkRange && $this->submittedValues['date_to'] < $this->submittedValues['date_from']) {
        // date to is smaller than date from
         $this->errorMessages['date_to'] = $this->t('message_validate_batch_bad_date_range');
      }
    }
  }


  /**
   * {@inheritdoc}
   *
   * Sends the invoices as defined by the form values to Acumulus.
   */
  protected function execute() {
    $type = $this->getFormValue('invoice_source_type');
    if ($this->getFormValue('invoice_source_reference_from')) {
      // Retrieve by order reference range.
      $from = $this->getFormValue('invoice_source_reference_from');
      $to = $this->getFormValue('invoice_source_reference_to') ? $this->getFormValue('invoice_source_reference_to') : $from;
      $invoiceSources = $this->invoiceManager->getInvoiceSourcesByReferenceRange($type, $from, $to);
    }
    else {
      // Retrieve by order date.
      $dateFormat = $this->getDateFormat();
      $from =  DateTime::createFromFormat($dateFormat, $this->getFormValue('date_from'));
      $to = $this->getFormValue('date_to') ? DateTime::createFromFormat($dateFormat, $this->getFormValue('date_from')) : $from;
      $invoiceSources = $this->invoiceManager->getInvoiceSourcesByDateRange($type, $from, $to);
    }
    if (count($invoiceSources) === 0) {
      $this->log[$type] = sprintf($this->t('message_form_empty_range'), $this->t($type));
      $result = TRUE;
    }
    else {
      $result = $this->invoiceManager->sendMultiple($invoiceSources, (bool) $this->getFormValue('force_send'), $this->log);
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    $fields = array();

    $invoiceSourceTypes = $this->invoiceManager->getSupportedInvoiceSourceTypes();
    if (count($invoiceSourceTypes) === 1) {
      // Make it a hidden field.
      $invoiceSourceTypeField = array(
        'type' => 'hidden',
        'value' => reset($invoiceSourceTypes),
      );
    }
    else {
      $options = array();
      foreach ($invoiceSourceTypes as $invoiceSourceType) {
        $options[$invoiceSourceType] = ucfirst($this->t($invoiceSourceType));
      }
      $invoiceSourceTypeField = array(
        'type' => 'radio',
        'label' => $this->t('field_invoice_source_type'),
        'options' => $options,
        'attributes' => array(
          'required' => true,
        ),
      );
    }
    // 1st fieldset: Batch options.
    $fields['batchFieldsHeader'] = array(
      'type' => 'fieldset',
      'legend' => $this->t('batchFieldsHeader'),
      'fields' => array(
        'invoice_source_type' => $invoiceSourceTypeField,
        'invoice_source_reference_from' => array(
          'type' => 'text',
          'label' => $this->t('field_invoice_source_reference_from'),
        ),
        'invoice_source_reference_to' => array(
          'type' => 'text',
          'label' => $this->t('field_invoice_source_reference_to'),
          'description' => count($invoiceSourceTypes) === 1 ? $this->t('desc_invoice_source_reference_from_to_1') : $this->t('desc_invoice_source_reference_from_to_2'),
        ),
        'date_from' => array(
          'type' => 'date',
          'label' => $this->t('field_date_from'),
          'format' => $this->getShopDateFormat(),
        ),
        'date_to' => array(
          'type' => 'date',
          'label' => $this->t('field_date_to'),
          'description' => sprintf($this->t('desc_date_from_to'), $this->getShopDateFormat()),
          'format' => $this->getShopDateFormat(),
        ),
        'force_send' => array(
          'type' => 'checkbox',
          'label' => $this->t('field_options'),
          'description' => $this->t('desc_batch_options'),
          'options' => array(
            'force_send' => $this->t('option_force_send'),
          )
        ),
      ),
    );

    // 2nd fieldset: Batch log.
    if (!empty($this->log)) {
      $logText = implode("\n", $this->log);
      $this->formValues['log'] = $logText;
      $fields['batchLogHeader'] = array(
        'type' => 'fieldset',
        'legend' => $this->t('batchLogHeader'),
        'fields' => array(
          'log' => array(
            'type' => 'textarea',
            'value' => $logText,
            'attributes' => array(
              'readonly' => TRUE,
              'rows' => min(10, count($this->log)),
              'style' => 'box-sizing: border-box; width: 100%; min-width: 32em;',
            ),
          ),
        ),
      );
    }

    // 3rd fieldset: Batch info.
    $fields['batchInfoHeader'] = array(
      'type' => 'fieldset',
      'legend' => $this->t('batchInfoHeader'),
      'fields' => array(
        'info' => array(
          'type' => 'markup',
          'value' => $this->t('batch_info'),
          'attributes' => array(
            'readonly' => true,
          ),
        ),
      ),
    );

    return $fields;
  }

}
