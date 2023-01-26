<?php
/**
 * Note: even though the note below is true, we ignore it. So a failing minimal
 * PHP version requirement will fail before it gets here.
 *
 * Note: As long as we want to check for a minimal PHP version via the
 * Requirements checking process provided by the classes below, and we want to
 * properly log and inform the user, we should not use language constructs
 * that are only available as of that minimum version in the following classes
 * (and their child classes):
 * - {@see Container}: creates instances of the below classes.
 * - {@see Requirements}: executes the checks.
 * - {@see \Siel\Acumulus\Config\ConfigUpgrade}: initiates the check.
 * - {@see Severity}: part of a failed check.
 * - {@see Message}: represents a failed check.
 * - {@see MessageCollection}: represents failed checks.
 * - {@see Log}: Logs failed checks.
 */

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

use function extension_loaded;

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
     *   requirements are met.
     *   The keys can be used as translation keys, but currently, no Dutch
     *   translations are available. The values (the messages) are in English.
     *   If a key start with message_error_, it is a fatal missing requirement,
     *   if it starts with message_warning_, it is a recommended requirement.
     */
    public function check(): array
    {
        $result = [];

        if (version_compare(phpversion(), '7.4', '<')) {
            $result['message_error_req_php'] = 'The minimally required PHP version for the Acumulus module is PHP 7.4 (and soon to become 8.0).';
        } elseif (version_compare(phpversion(), '8.0', '<')) {
            $result['message_warning_req_php'] = 'The minimally required PHP version for the Acumulus module will soon be raised to PHP 8.0. Start upgrading your PHP version now';
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
