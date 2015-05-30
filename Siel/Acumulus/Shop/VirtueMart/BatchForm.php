<?php
namespace Siel\Acumulus\Shop\VirtueMart;

use JSession;
use JText;
use Siel\Acumulus\Shop\BatchForm as BaseBatchForm;

/**
 * Provides the Batch send form handling for the VirtueMart Acumulus module.
 */
class BatchForm extends BaseBatchForm {

  /**
   * {@inheritdoc}
   *
   * This override checks the Joomla form token:
   * https://docs.joomla.org/How_to_add_CSRF_anti-spoofing_to_forms
   */
  protected function systemValidate() {
    return JSession::checkToken();
  }

  public function getDateFormat() {
    return JText::_('DATE_FORMAT_LC4');
  }

  public function getShopDateFormat() {
    return str_replace(array('Y', 'm', 'd'), array('%Y', '%m', '%d'), $this->getDateFormat());
  }

}
