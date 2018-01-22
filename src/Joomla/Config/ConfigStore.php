<?php
namespace Siel\Acumulus\Joomla\Config;

use JComponentHelper;
use JTable;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the Joomla config component.
 */
class ConfigStore extends BaSeConfigStore
{
    /** @var array */
    protected $savedValues = array();

    /**
     * {@inheritdoc}
     */
    public function load(array $keys)
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
    public function save(array $values)
    {
        // When the values are loaded in the same request, the new values are
        // not retrieved: store a copy of them here to merge them when loading.
        $this->savedValues = $values;

        // Get the currently stored values.
        $component = JComponentHelper::getComponent('com_acumulus');
        if (get_class($component) === 'Joomla\CMS\Component\ComponentRecord' || get_class($component) === 'JComponentRecord') {
            // Joomla 3.7 (JComponentRecord) and 3.8+ (Joomla\CMS\Component\ComponentRecord)
            /** @var \Joomla\CMS\Component\ComponentRecord|\JComponentRecord $component */
            $data = $component->getParams()->toArray();
            $id = $component->id;
        } else {
            // Joomla 3.6.x-
            /** @var \stdClass|array $component */
            $component = (array) $component;
            /** @var \Joomla\Registry\Registry $params */
            $params = $component['params'];
            $data = $params->toArray();
            $id = $component['id'];
        }

        // Update the values with the form values.
        $defaults = $this->acumulusConfig->getDefaults();
        foreach ($values as $key => $value) {
            if ((isset($defaults[$key]) && $defaults[$key] === $value) || $value === null) {
                unset($data[$key]);
            }
            else {
                $data[$key] = $value;
            }
        }

        // Store the values directly in the extension table.
        $table = JTable::getInstance('extension');
        $result = $table->load($id);
        if ($result) {
            // Data is stored as a json encoded array.
            $table->set('params', json_encode($data));
            $result = $table->store();
        }
        return $result;
    }
}
