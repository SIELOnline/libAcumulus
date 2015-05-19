<?php
namespace Siel\Acumulus\Shop\VirtueMart;

use JSession;
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

}
