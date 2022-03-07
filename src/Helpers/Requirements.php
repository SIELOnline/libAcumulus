<?php
/**
 * Note: Do not use PHP7 language constructs in the {@see Container} class as
 * long as we want the {@see Requirements} class to check for that, initiated by
 * the {@see \Siel\Acumulus\Config\ConfigUpgrade} class, and present and
 * {@see Log} proper warnings.
 *
 * @noinspection PhpMissingParamTypeInspection
 * @noinspection PhpMissingReturnTypeInspection
 * @noinspection PhpMissingFieldTypeInspection
 * @noinspection PhpMissingVisibilityInspection
 */

namespace Siel\Acumulus\Helpers;

/**
 * Defines and checks the requirements for this library. Used on installation.
 *
 * Override if the library for your web shop has additional requirements.
 */
class Requirements
{
    /**
     * Checks if the requirements for the environment are met.
     *
     * Note: we cannot use MessageCollection as we use php 5.6 specific features
     * in its dependencies.
     *
     * @return string[]
     *   An array with messages regarding missing requirements, empty if all
     *   requirements are met. The keys can be used as translation keys, but
     *   currently, no Dutch translations are available. The values are in
     *   English.
     */
    public function check()
    {
        $result = [];

        if (version_compare(PHP_VERSION, '7.1', '<')) {
            $result['message_error_req_php'] = 'The minimally required PHP version for the Acumulus module is PHP 7.1 (and soon to become 7.4).';
        }
        if (!extension_loaded('json')) {
            $result['message_error_req_json'] = 'The JSON PHP extension needs to be activated on your server for the Acumulus module to be able to work with the json format.';
        }
        if (!extension_loaded('curl')) {
            $result['message_error_req_curl'] = 'The CURL PHP extension needs to be activated on your server for the Acumulus module to be able to connect with the Acumulus server.';
        }
        if (!extension_loaded('libxml')) {
            $result['message_error_req_libxml'] = 'The libxml extension needs to be activated on your server for the Acumulus module to be able to work with the XML format.';
        }
        if (!extension_loaded('dom')) {
            $result['message_error_req_dom'] = 'The DOM PHP extension needs to be activated on your server for the Acumulus module to work with the XML format.';
        }
        if (!extension_loaded('simplexml')) {
            $result['message_error_req_simplexml'] = 'The SimpleXML extension needs to be activated on your server for the Acumulus module to be able to work with the XML format.';
        }

        return $result;
    }
}
