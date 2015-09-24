<?php
namespace Siel\Acumulus\PrestaShop\Shop;

use Siel\Acumulus\Shop\BatchForm as BaseBatchForm;
use Tools;

/**
 * Provides the Batch send form handling for the VirtueMart Acumulus module.
 */
class BatchForm extends BaseBatchForm {

  public function isSubmitted() {
    return Tools::isSubmit('submitAdd');
  }

  /**
   * {@inheritdoc}
   */
  protected function setFormValues() {
    parent::setFormValues();

    // Prepend (checked) checkboxes with their collection name.
    foreach ($this->getCheckboxKeys() as $checkboxName => $collectionName) {
      if (isset($this->formValues[$checkboxName])) {
        $this->formValues["{$collectionName}_{$checkboxName}"] = $this->formValues[$checkboxName];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDefinitions() {
    $result = parent::getFieldDefinitions();
    $result['batchFieldsHeader']['icon'] = 'icon-envelope-alt';
    if (isset($result['batchLogHeader'])) {
      $result['batchLogHeader']['icon'] = 'icon-list';
    }
    $result['batchInfoHeader']['icon'] = 'icon-info-circle';
    return $result;
  }

}
