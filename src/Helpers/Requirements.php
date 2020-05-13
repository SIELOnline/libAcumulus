<?php
namespace Siel\Acumulus\Helpers;

/**
 * Defines and checks the requirements for this library. Used on install.
 *
 * Override if the library for your webshop has additional requirements.
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
     *   requirements are met. The keys are the translations keys, the values are
     *   the English translations.
     */
    public function check()
    {
        $result = array();

        // PHP 5.3 is a requirement as well because we use namespaces. But as
        // the parser will already have failed fatally before we get here, it
        // makes no sense to check that here.
        if (version_compare(PHP_VERSION, '5.6', '<')) {
            $result['message_error_req_php'] = 'The minimally required PHP version for the Acumulus module is PHP 5.6.';
        }
        if (!extension_loaded('json')) {
            $result['message_error_req_json'] = 'The JSON PHP extension needs to be activated on your server for the Acumulus module to be able to work with the json format.';
        }
        if (!extension_loaded('curl')) {
            $result['message_error_req_curl'] = 'The CURL PHP extension needs to be activated on your server for the Acumulus module to beable to connect with the Acumulus server.';
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
