<?php
namespace Siel\Acumulus\Joomla\Shop;

use JSession;
use Siel\Acumulus\Shop\ConfigForm as BaseConfigForm;

/**
 * Class ConfigForm adds features for the settings form page for the Joomla
 * based shop modules.
 */
abstract class ConfigForm extends BaseConfigForm {

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
