<?php
namespace Siel\Acumulus\Joomla\Shop;

use JComponentHelper;
use JFactory;
use JLoader;
use JModelLegacy;
use JTable;
use JTableExtension;
use Siel\Acumulus\Shop\ConfigStoreInterface;

/**
 * Implements the connection to the Joomla config component.
 */
class ConfigStore implements ConfigStoreInterface {

  /** @var array */
  protected $savedValues = array();

  /**
   * {@inheritdoc}
   */
  public function getShopEnvironment() {
    /** @var JTableExtension $extension */
    $extension = JTable::getInstance('extension');

    $id = $extension->find(array('element' => 'com_acumulus'));
    $extension->load($id);
    $componentInfo = json_decode($extension->manifest_cache, true);
    $moduleVersion = $componentInfo['version'];

    if ($this->isEnabled('com_virtuemart')) {
      $shopName = 'VirtueMart';
    }
    else /*if ($this->isEnabled('com_hikashop'))*/ {
      $shopName = 'HikaShop';
    }
    $id = $extension->find(array('element' => 'com_' . strtolower($shopName)));
    $extension->load($id);
    $componentInfo = json_decode($extension->manifest_cache, true);
    $shopVersion = $componentInfo['version'];

    $joomlaVersion = JVERSION;

    $environment = array(
      'moduleVersion' => $moduleVersion,
      'shopName' => $shopName,
      'shopVersion' => "$shopVersion (CMS: Joomla $joomlaVersion)",
    );

    return $environment;
  }

  /**
   * Checks if a component is installed and enabled.
   *
   * Note that JComponentHelper::isEnabled shows a warning if the component is
   * not installed, which we don't want.
   *
   * @param string $component
   *   The element/name of the extension.
   *
   * @return bool
   *   True if the extension is installed and enabled, false otherwise
   */
  protected function isEnabled($component) {
    $db = JFactory::getDbo();
    $db->setQuery(sprintf("SELECT enabled FROM #__extensions WHERE element = '%s'", $db->escape($component)));
    $enabled = $db->loadResult();
    return $enabled == 1;
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
