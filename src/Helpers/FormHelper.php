<?php
namespace Siel\Acumulus\Helpers;

/**
 * Provides basic form helper features.
 */
class FormHelper
{
    /**
     * Indicates whether the current form handling is a form submission.
     *
     * @return bool
     */
    public function isSubmitted()
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Returns a flat array of the posted values.
     *
     * As especially checkbox handling differs per webshop, often resulting in
     * an array of checkbox values, this method returns a flattened version of
     * the posted values.
     *
     * @param array $checkboxKeys
     *   A list of keys that represent checkboxes and therefore may need special
     *   treatment
     *
     * @return array
     */
    public function getPostedValues(array $checkboxKeys)
    {
        $result = $_POST;

        // Handle checkboxes.
        foreach ($checkboxKeys as $checkboxName => $collectionName) {
            if (isset($result[$collectionName]) && is_array($result[$collectionName])) {
                // Checkboxes are handled as an array of checkboxes. Extract the
                // checked values.
                // Known usages: Magento
                $checkedValues = array_combine(array_values($result[$collectionName]), array_fill(0, count($result[$collectionName]), 1));
                $result += $checkedValues;
            } elseif (isset($result["{$collectionName}_{$checkboxName}"])) {
                // Checkbox keys are prepended with their collection name: add
                // value for key without collection name.
                // Known usages: PrestaShop
                $result[$checkboxName] = $result["{$collectionName}_{$checkboxName}"];
            }
        }

        return $result;
    }
}
