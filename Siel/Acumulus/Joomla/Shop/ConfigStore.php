<?php
namespace Siel\Acumulus\Joomla\Shop;

use JComponentHelper;
use JLoader;
use JModelLegacy;
use JTable;
use JTableExtension;
use Siel\Acumulus\Shop\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Joomla config component.
 */
class ConfigStore extends BaSeConfigStore {

  /** @var array */
  protected $savedValues = array();

  /**
   * {@inheritdoc}
   */
  public function getShopEnvironment() {
    /** @var JTableExtension $extension */
    $extension = JTable::getInstance('extension');

    $id = $extension->find(array('element' => 'com_acumulus', 'type' => 'component'));
    $extension->load($id);
    /** @noinspection PhpUndefinedFieldInspection */
    $componentInfo = json_decode($extension->manifest_cache, true);
    $moduleVersion = $componentInfo['version'];

    $id = $extension->find(array('element' => 'com_' . strtolower($this->shopName), 'type' => 'component'));
    $extension->load($id);
    /** @noinspection PhpUndefinedFieldInspection */
    $componentInfo = json_decode($extension->manifest_cache, true);
    $shopVersion = $componentInfo['version'];

    $joomlaVersion = JVERSION;

    $environment = array(
      'moduleVersion' => $moduleVersion,
      'shopName' => $this->shopName,
      'shopVersion' => "$shopVersion (CMS: Joomla $joomlaVersion)",
    );

    return $environment;
  }

  /**
   * {@inheritdoc}
   */
  public function load(array $keys) {
    $result = array();
    $params = JComponentHelper::getParams('com_acumulus');
    foreach ($keys as $key) {
      $value = $params->get($key, null);;
      if (isset($value)) {
        $result[$key] = $value;
      }
      // Overwrite with values saved during this request.
      if (isset($this->savedValues[$key])) {
        $result[$key] = $this->savedValues[$key];
      }
    }
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $values) {
    // When the values are loaded in the same request, the new values are not
    // retrieved: store a copy of them here to merge them when loading.
    $this->savedValues = $values;

    // Place the values in the component record.
    /** @var \stdClass|array $component */
    $component = JComponentHelper::getComponent('com_acumulus');
    $component = (array) $component;
    $component['params'] = empty($component['params']['data']) ? $values : array_merge($component['params']['data'], $values);

    // Use save method of com_config component model.
    JLoader::registerPrefix('Config', JPATH_ROOT . '/components/com_config');
    JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'com_config' . DIRECTORY_SEPARATOR . 'model');
    /** @var \ConfigModelComponent $model */
    $model = JModelLegacy::getInstance('Component', 'ConfigModel', array('ignore_request' => true));
    return $model->save($component);
  }

}
