<?php
namespace Siel\Acumulus\Helpers;

/**
 * Defines and checks the requirements for this library. Used on install.
 */
class Requirements
{
    /**
     * Checks if the requirements for the environment are met.
     *
     * @return string[]
     *   An array with messages regarding missing requirements, empty if all
     *   requirements are met. The keys are the translations keys, the values are
     *   the English translations.
     */
    static public function check()
    {
        $result = array();

        // PHP 5.3 is a requirement as well because we use namespaces. But as
        // the parser will already have failed fatally before we get here, it
        // makes no sense to check that here.
        if (!extension_loaded('curl')) {
            $result['message_error_req_curl'] = 'The CURL PHP extension needs to be activated on your server for the Acumulus module to work.';
        }
        if (!extension_loaded('simplexml')) {
            $result['message_error_req_xml'] = 'The SimpleXML extension needs to be activated on your server for the Acumulus module to be able to work with the XML format.';
        }
        if (!extension_loaded('dom')) {
            $result['message_error_req_dom'] = 'The DOM PHP extension needs to be activated on your server for the Acumulus module to work.';
        }

        return $result;
    }
}
