<?php
namespace Siel\Acumulus\Joomla\Config;

use JComponentHelper;
use JFactory;
use JTable;
use JTableExtension;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Joomla config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /**
     * {@inheritdoc}
     */
    public function load()
    {
        $extensionTable = new JtableExtension(JFactory::getDbo());
        $extensionTable->load(array('element' => 'com_acumulus'));
        $values = $extensionTable->get('custom_data');
        $values = !empty($values) ? json_decode($values, true) : array();

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        $extensionTable = new JtableExtension(JFactory::getDbo());
        $extensionTable->load(array('element' => 'com_acumulus'));
        $extensionTable->set('custom_data', json_encode($values, JSON_FORCE_OBJECT));
        $result = $extensionTable->store();
        return $result;
    }

    /**
     * @deprecated Only still here for use during update.
     *
     * @param array $keys
     *
     * @return array
     */
    public function loadOld(array $keys)
    {
        $result = array();
        $params = JComponentHelper::getParams('com_acumulus');
        foreach ($keys as $key) {
            $value = $params->get($key, null);
            if (isset($value)) {
                // Keyed arrays are saved as objects, convert back to an array.
                if (is_object($value)) {
                    // http://php.net/manual/en/language.types.array.php:
                    // If an object is converted to an array, the result is an
                    // array whose elements are the object's properties. The
                    // keys are the member variable names, with a few notable
                    // exceptions: integer properties are not accessible!
                    // So we use this strange looking but working conversion.
                    $value = json_decode(json_encode($value), true);
                }
                $result[$key] = $value;
            }
        }

        // Delete the values, this will only be used one more time: during
        // updating to 5.4.0.
        $extensionTable = new JtableExtension(JFactory::getDbo());
        $extensionTable->load(array('element' => 'com_acumulus'));
        $extensionTable->set('params', '');
        $extensionTable->store();

        return $result;
    }
}
