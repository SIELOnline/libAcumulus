<?php
/**
 * Note: As long as we want to check for a minimal PHP version via the
 * Requirements checking process provided by the classes below, and we want to
 * properly log and inform the user, we should not use PHP7 language constructs
 * in the following classes (and its child classes):
 * - {@see Container}: creates instances of the below classes.
 * - {@see Requirements}: executes the checks.
 * - {@see \Siel\Acumulus\Config\ConfigUpgrade}: initiates the check.
 * - {@see \Siel\Acumulus\Helpers\Severity}: part of a failed check.
 * - {@see \Siel\Acumulus\Helpers\Message}: represents a failed check.
 * - {@see \Siel\Acumulus\Helpers\MessageCollection}: represents failed checks.
 * - {@see Log}: Logs failed checks.
 *
 * The PHP7 language constructs we suppress the warnings for:
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
     * @return string[]
     *   An array with messages regarding missing requirements, empty if all
     *   requirements are met. The keys can be used as translation keys, but
     *   currently, no Dutch translations are available. The values are in
     *   English.
     */
    public function check()
    {
        $result = [];

        if (version_compare(PHP_VERSION, '7.2', '<')) {
            $result['message_error_req_php'] = 'The minimally required PHP version for the Acumulus module is PHP 7.2 (and soon to become 7.4).';
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
